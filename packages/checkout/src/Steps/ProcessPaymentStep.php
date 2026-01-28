<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Processing;
use Illuminate\Support\Facades\Route;

final class ProcessPaymentStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly PaymentGatewayResolverInterface $paymentResolver,
    ) {}

    public function getIdentifier(): string
    {
        return 'process_payment';
    }

    public function getName(): string
    {
        return 'Process Payment';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['calculate_shipping'];
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        if ($session->grand_total <= 0) {
            // Free order - no payment needed
            return $errors;
        }

        $gateway = $session->selected_payment_gateway ?? config('checkout.payment.default_gateway');

        if ($gateway !== null && ! $this->paymentResolver->hasGateway($gateway)) {
            $errors['payment_gateway'] = "Selected payment gateway '{$gateway}' is not available";
        }

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        // Handle free orders (e.g., 100% discount)
        if ($session->grand_total <= 0) {
            $session->update([
                'payment_data' => [
                    'type' => 'free_order',
                    'processed_at' => now()->toIso8601String(),
                ],
            ]);
            $session->status->transitionTo(Processing::class);

            return $this->success('No payment required - free order');
        }

        $gateway = $session->selected_payment_gateway;
        $processor = $this->paymentResolver->resolve($gateway);

        // Check if processor is available for this session
        if (! $processor->isAvailable($session)) {
            return $this->failed('Selected payment method is not available', [
                'payment' => 'Payment method not available for this order',
            ]);
        }

        // Build payment request
        $paymentRequest = $this->buildPaymentRequest($session);

        // Increment payment attempts
        $session->update([
            'payment_attempts' => $session->payment_attempts + 1,
            'selected_payment_gateway' => $processor->getIdentifier(),
        ]);
        $session->status->transitionTo(PaymentProcessing::class);

        // Process payment
        $result = $processor->createPayment($session, $paymentRequest);

        // Handle payment result
        $paymentData = [
            'gateway' => $processor->getIdentifier(),
            'payment_id' => $result->paymentId,
            'transaction_id' => $result->transactionId,
            'status' => $result->status->value,
            'amount' => $result->amount ?? $session->grand_total,
            'currency' => $result->currency ?? $session->currency,
            'gateway_response' => $result->gatewayResponse,
            'processed_at' => now()->toIso8601String(),
        ];

        $session->update([
            'payment_id' => $result->paymentId,
            'payment_data' => $paymentData,
        ]);

        // Handle redirect-based payments
        if ($result->requiresRedirect()) {
            $session->update(['payment_redirect_url' => $result->redirectUrl]);
            $session->status->transitionTo(AwaitingPayment::class);

            return $this->success('Payment initiated - redirect required', [
                'redirect_url' => $result->redirectUrl,
                'payment_id' => $result->paymentId,
            ]);
        }

        // Handle immediate success
        if ($result->isSuccessful()) {
            event(new \AIArmada\Checkout\Events\CheckoutPaymentCompleted(
                session: $session,
                paymentData: $paymentData,
            ));
            $session->status->transitionTo(Processing::class);

            return $this->success('Payment completed', [
                'payment_id' => $result->paymentId,
                'transaction_id' => $result->transactionId,
            ]);
        }

        // Handle failure
        $session->status->transitionTo(PaymentFailed::class);

        return $this->failed($result->message ?? 'Payment failed', $result->errors);
    }

    public function rollback(CheckoutSession $session): void
    {
        // Payment rollback is typically handled via refunds
        // This is a no-op as we don't want to automatically refund on rollback
        // Refunds should be explicit actions
    }

    private function buildPaymentRequest(CheckoutSession $session): PaymentRequest
    {
        $billingData = $session->billing_data ?? [];
        $customer = $session->customer;

        $customerName = $billingData['name'] ?? null;
        if ($customerName === null && $customer !== null) {
            $customerName = $customer->full_name ?? $customer->first_name ?? null;
        }

        return new PaymentRequest(
            amount: $session->grand_total,
            currency: $session->currency,
            gateway: $session->selected_payment_gateway,
            description: "Order checkout - Session {$session->id}",
            customerEmail: $billingData['email'] ?? $customer?->email,
            customerName: $customerName,
            customerPhone: $billingData['phone'] ?? $customer?->phone,
            successUrl: $this->buildCallbackUrl('success', $session),
            failureUrl: $this->buildCallbackUrl('failure', $session),
            cancelUrl: $this->buildCallbackUrl('cancel', $session),
            metadata: [
                'checkout_session_id' => $session->id,
                'cart_id' => $session->cart_id,
                'customer_id' => $session->customer_id,
            ],
        );
    }

    private function buildCallbackUrl(string $type, CheckoutSession $session): string
    {
        $routeName = match ($type) {
            'success' => 'checkout.payment.success',
            'failure' => 'checkout.payment.failure',
            'cancel' => 'checkout.payment.cancel',
            default => 'checkout.payment.success',
        };

        // Use route if available, fall back to config path
        if (Route::has($routeName)) {
            return route($routeName, ['session' => $session->id]);
        }

        $configKey = match ($type) {
            'success' => 'checkout.payment.success_redirect',
            'failure' => 'checkout.payment.failure_redirect',
            'cancel' => 'checkout.payment.cancel_redirect',
            default => 'checkout.payment.success_redirect',
        };

        $path = config($configKey, "/checkout/{$type}");
        $separator = str_contains($path, '?') ? '&' : '?';

        return url($path . $separator . 'session=' . $session->id);
    }
}
