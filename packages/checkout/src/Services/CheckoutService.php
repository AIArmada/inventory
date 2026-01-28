<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\SessionDataTransformerInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutCancelled;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutStarted;
use AIArmada\Checkout\Events\CheckoutStepCompleted;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Exceptions\PaymentException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Transformers\NullSessionDataTransformer;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class CheckoutService implements CheckoutServiceInterface
{
    public function __construct(
        private readonly CheckoutStepRegistryInterface $stepRegistry,
        private readonly Dispatcher $events,
        private readonly ?PaymentGatewayResolverInterface $paymentResolver = null,
    ) {}

    public function startCheckout(string $cartId, ?string $customerId = null): CheckoutSession
    {
        $cart = $this->resolveCart($cartId);

        if ($cart === null) {
            throw InvalidCheckoutStateException::cartNotFound($cartId);
        }

        if ($cart->isEmpty()) {
            throw InvalidCheckoutStateException::emptyCart($cartId);
        }

        $session = CheckoutSession::create([
            'cart_id' => $cartId,
            'customer_id' => $customerId,
            'status' => Pending::class,
            'cart_snapshot' => $this->createCartSnapshot($cart),
            'step_states' => $this->initializeStepStates(),
            'current_step' => $this->getFirstStepIdentifier(),
            'expires_at' => now()->addSeconds(config('checkout.defaults.session_ttl', 86400)),
        ]);

        $this->events->dispatch(new CheckoutStarted($session));

        return $session;
    }

    public function resumeCheckout(string $sessionId): CheckoutSession
    {
        $session = CheckoutSession::find($sessionId);

        if ($session === null) {
            throw InvalidCheckoutStateException::sessionNotFound($sessionId);
        }

        if ($session->isExpired()) {
            throw InvalidCheckoutStateException::sessionExpired($sessionId);
        }

        return $session;
    }

    public function processCheckout(CheckoutSession $session): CheckoutResult
    {
        if ($session->status->isTerminal()) {
            throw InvalidCheckoutStateException::cannotModify($session->id, $session->status->name());
        }

        $this->transformSessionData($session);

        $session->status->transitionTo(Processing::class);

        try {
            return DB::transaction(function () use ($session) {
                foreach ($this->stepRegistry->getOrderedSteps() as $step) {
                    $stepState = $session->getStepState($step->getIdentifier());

                    // Skip already completed steps
                    if ($stepState === StepStatus::Completed || $stepState === StepStatus::Skipped) {
                        continue;
                    }

                    // Check if step can be skipped
                    if ($step->canSkip($session)) {
                        $session->setStepState($step->getIdentifier(), StepStatus::Skipped);

                        continue;
                    }

                    // Process the step
                    $result = $this->processStepInternal($session, $step);

                    if (! $result->isSuccessful()) {
                        return CheckoutResult::failed($session, $result->message ?? 'Step failed', $result->errors);
                    }

                    // Check for payment redirect
                    if ($step->getIdentifier() === 'process_payment' && $session->payment_redirect_url !== null) {
                        return CheckoutResult::awaitingPayment($session, $session->payment_redirect_url);
                    }
                }

                $session->status->transitionTo(Completed::class);
                $this->events->dispatch(new CheckoutCompleted($session));

                return CheckoutResult::success($session);
            });
        } catch (Throwable $e) {
            $this->handleCheckoutFailure($session, $e);

            throw $e;
        }
    }

    public function processStep(CheckoutSession $session, string $stepName): CheckoutSession
    {
        $step = $this->stepRegistry->get($stepName);

        if ($step === null) {
            throw CheckoutStepException::stepNotFound($stepName);
        }

        if (! $this->stepRegistry->isEnabled($stepName)) {
            throw CheckoutStepException::stepNotFound($stepName);
        }

        $this->transformSessionData($session);

        $this->processStepInternal($session, $step);

        return $session->fresh();
    }

    public function retryPayment(CheckoutSession $session): CheckoutResult
    {
        if (! $session->status->canRetryPayment()) {
            throw InvalidCheckoutStateException::cannotModify($session->id, $session->status->name());
        }

        $retryLimit = config('checkout.payment.retry_limit', 3);
        if ($session->payment_attempts >= $retryLimit) {
            throw PaymentException::retryLimitExceeded($session->payment_attempts, $retryLimit);
        }

        // Reset payment step state
        $session->setStepState('process_payment', StepStatus::Pending);
        $session->update(['payment_attempts' => $session->payment_attempts + 1]);
        $session->status->transitionTo(Processing::class);

        // Re-process from payment step
        $paymentStep = $this->stepRegistry->get('process_payment');
        if ($paymentStep === null) {
            throw CheckoutStepException::stepNotFound('process_payment');
        }

        $result = $this->processStepInternal($session, $paymentStep);

        if ($result->isSuccessful()) {
            // Continue with remaining steps
            return $this->continueFromStep($session, 'process_payment');
        }

        if ($session->payment_redirect_url !== null) {
            return CheckoutResult::awaitingPayment($session, $session->payment_redirect_url);
        }

        return CheckoutResult::failed($session, $result->message ?? 'Payment failed', $result->errors);
    }

    public function cancelCheckout(CheckoutSession $session): CheckoutSession
    {
        if (! $session->status->canCancel()) {
            throw InvalidCheckoutStateException::cannotCancel($session->id, $session->status->name());
        }

        // Rollback completed steps in reverse order
        $this->rollbackCompletedSteps($session);

        $session->status->transitionTo(Cancelled::class);

        $this->events->dispatch(new CheckoutCancelled($session));

        return $session->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handlePaymentCallback(
        CheckoutSession $session,
        string $callbackType,
        array $payload = [],
    ): CheckoutResult {
        // Handle cancellation
        if ($callbackType === 'cancel') {
            if ($session->status->canCancel()) {
                $session->status->transitionTo(Cancelled::class);
                $this->events->dispatch(new CheckoutCancelled($session));
            }

            return CheckoutResult::failed($session, 'Payment was cancelled');
        }

        // Handle failure
        if ($callbackType === 'failure') {
            $session->status->transitionTo(PaymentFailed::class);
            $session->update(['error_message' => 'Payment failed at gateway']);
            $this->events->dispatch(new CheckoutFailed($session, 'Payment failed'));

            return CheckoutResult::failed($session, 'Payment failed');
        }

        // Handle success - verify payment and complete checkout
        if ($callbackType === 'success') {
            return $this->verifyAndCompletePayment($session, $payload);
        }

        return CheckoutResult::failed($session, 'Unknown callback type');
    }

    public function getCurrentStep(CheckoutSession $session): ?string
    {
        return $session->current_step;
    }

    public function canProceed(CheckoutSession $session): bool
    {
        if ($session->status->isTerminal()) {
            return false;
        }

        if ($session->isExpired()) {
            return false;
        }

        $currentStep = $this->stepRegistry->get($session->current_step ?? '');

        if ($currentStep === null) {
            return false;
        }

        return empty($currentStep->validate($session));
    }

    /**
     * @return \AIArmada\Cart\Cart|null
     */
    private function resolveCart(string $cartId): mixed
    {
        if (! app()->bound(CartManagerInterface::class)) {
            return null;
        }

        return app(CartManagerInterface::class)->getById($cartId);
    }

    /**
     * @return array<string, mixed>
     */
    private function createCartSnapshot(mixed $cart): array
    {
        return [
            'items' => $cart->getItems()->toArray(),
            'subtotal' => $cart->subtotal()->getAmount(),
            'total' => $cart->total()->getAmount(),
            'item_count' => $cart->countItems(),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function initializeStepStates(): array
    {
        $states = [];

        foreach ($this->stepRegistry->getEnabledStepIdentifiers() as $identifier) {
            $states[$identifier] = StepStatus::Pending->value;
        }

        return $states;
    }

    private function getFirstStepIdentifier(): ?string
    {
        $steps = $this->stepRegistry->getOrderedSteps();

        return ! empty($steps) ? $steps[0]->getIdentifier() : null;
    }

    private function processStepInternal(CheckoutSession $session, CheckoutStepInterface $step): \AIArmada\Checkout\Data\StepResult
    {
        $identifier = $step->getIdentifier();

        // Validate dependencies
        foreach ($step->getDependencies() as $dependency) {
            $depState = $session->getStepState($dependency);
            if ($depState !== StepStatus::Completed && $depState !== StepStatus::Skipped) {
                throw CheckoutStepException::dependencyNotMet($identifier, $dependency);
            }
        }

        // Validate step
        $errors = $step->validate($session);
        if (! empty($errors)) {
            $session->setStepState($identifier, StepStatus::Failed);
            $this->events->dispatch(new CheckoutStepFailed($session, $identifier, $errors));

            return \AIArmada\Checkout\Data\StepResult::failed($identifier, 'Validation failed', $errors);
        }

        // Execute step
        $session->setStepState($identifier, StepStatus::Processing);
        $session->update(['current_step' => $identifier]);

        $result = $step->handle($session);

        $session->setStepState($identifier, $result->status);

        if ($result->isSuccessful()) {
            $this->events->dispatch(new CheckoutStepCompleted($session, $identifier, $result->data));
        } else {
            $this->events->dispatch(new CheckoutStepFailed($session, $identifier, $result->errors));
        }

        return $result;
    }

    private function continueFromStep(CheckoutSession $session, string $fromStep): CheckoutResult
    {
        $steps = $this->stepRegistry->getOrderedSteps();
        $startProcessing = false;

        foreach ($steps as $step) {
            if ($step->getIdentifier() === $fromStep) {
                $startProcessing = true;

                continue;
            }

            if (! $startProcessing) {
                continue;
            }

            $stepState = $session->getStepState($step->getIdentifier());
            if ($stepState === StepStatus::Completed || $stepState === StepStatus::Skipped) {
                continue;
            }

            if ($step->canSkip($session)) {
                $session->setStepState($step->getIdentifier(), StepStatus::Skipped);

                continue;
            }

            $result = $this->processStepInternal($session, $step);

            if (! $result->isSuccessful()) {
                return CheckoutResult::failed($session, $result->message ?? 'Step failed', $result->errors);
            }
        }

        $session->status->transitionTo(Completed::class);
        $this->events->dispatch(new CheckoutCompleted($session));

        return CheckoutResult::success($session);
    }

    private function rollbackCompletedSteps(CheckoutSession $session): void
    {
        $steps = array_reverse($this->stepRegistry->getOrderedSteps());

        foreach ($steps as $step) {
            $state = $session->getStepState($step->getIdentifier());

            if ($state === StepStatus::Completed) {
                $step->rollback($session);
                $session->setStepState($step->getIdentifier(), StepStatus::RolledBack);
            }
        }
    }

    private function handleCheckoutFailure(CheckoutSession $session, Throwable $e): void
    {
        $session->status->transitionTo(PaymentFailed::class);
        $session->update(['error_message' => $e->getMessage()]);

        $this->events->dispatch(new CheckoutFailed($session, $e->getMessage()));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifyAndCompletePayment(CheckoutSession $session, array $payload): CheckoutResult
    {
        // If webhook payload provided, use it; otherwise verify with gateway
        $paymentVerified = false;

        if (! empty($payload)) {
            // Trust webhook payload - gateway has already verified
            $status = $payload['status'] ?? $payload['data']['object']['status'] ?? null;
            $paymentVerified = in_array($status, ['paid', 'completed', 'succeeded', 'complete'], true);
        } elseif ($this->paymentResolver !== null && $session->payment_id !== null) {
            // Verify payment status with gateway
            $gateway = $session->selected_payment_gateway;
            $processor = $this->paymentResolver->resolve($gateway);
            $result = $processor->checkStatus($session->payment_id);

            $paymentVerified = $result->status === PaymentStatus::Completed;

            // Update payment data with verification result
            $session->update([
                'payment_data' => array_merge($session->payment_data ?? [], [
                    'verified_at' => now()->toIso8601String(),
                    'verification_status' => $result->status->value,
                ]),
            ]);
        }

        if (! $paymentVerified) {
            // Payment not verified - stay in awaiting state or mark failed
            return CheckoutResult::failed($session, 'Payment could not be verified');
        }

        $this->dispatchPaymentCompleted($session);

        // Payment confirmed - mark payment step complete and continue
        $session->setStepState('process_payment', StepStatus::Completed);
        $session->status->transitionTo(Processing::class);
        $session->update(['payment_redirect_url' => null]); // Clear redirect URL

        // Continue with remaining steps (create_order, etc.)
        return $this->continueFromStep($session, 'process_payment');
    }

    private function transformSessionData(CheckoutSession $session): void
    {
        $billingData = $this->transformData('billing', $session->billing_data ?? [], $session);
        $shippingData = $this->transformData('shipping', $session->shipping_data ?? [], $session);

        $updates = [];

        if ($billingData !== ($session->billing_data ?? [])) {
            $updates['billing_data'] = $billingData;
        }

        if ($shippingData !== ($session->shipping_data ?? [])) {
            $updates['shipping_data'] = $shippingData;
        }

        if ($updates !== []) {
            $session->update($updates);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function transformData(string $type, array $data, CheckoutSession $session): array
    {
        $transformerClass = config("checkout.transformers.{$type}", NullSessionDataTransformer::class);

        $transformer = app($transformerClass);

        if (! $transformer instanceof SessionDataTransformerInterface) {
            throw new RuntimeException("Checkout {$type} transformer must implement " . SessionDataTransformerInterface::class);
        }

        return $transformer->transform($data, $session);
    }

    private function dispatchPaymentCompleted(CheckoutSession $session): void
    {
        $paymentData = $session->payment_data ?? [];

        $this->events->dispatch(new \AIArmada\Checkout\Events\CheckoutPaymentCompleted(
            session: $session,
            paymentData: is_array($paymentData) ? $paymentData : [],
        ));
    }
}
