<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Http\Controllers;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\WebhookHandled;
use AIArmada\CashierChip\Events\WebhookReceived;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a CHIP webhook call.
     */
    public function __invoke(Request $request): Response
    {
        $signatureFailure = $this->signatureFailureResponse($request);

        if ($signatureFailure !== null) {
            return $signatureFailure;
        }

        $payload = $request->all();

        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            $owner = ChipWebhookOwnerResolver::resolveFromPayload($payload);

            if ($owner === null) {
                Log::channel(config('cashier-chip.logger', config('logging.default', 'stack')))
                    ->error('cashier-chip webhook received but no owner could be resolved for brand_id', [
                        'event_type' => $payload['event_type'] ?? null,
                        'brand_id' => $payload['brand_id'] ?? ($payload['purchase']['brand_id'] ?? null),
                    ]);

                return new Response('Owner resolution failed', 500);
            }

            return OwnerContext::withOwner($owner, fn (): Response => $this->handleScoped($payload));
        }

        return $this->handleScoped($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleScoped(array $payload): Response
    {

        WebhookReceived::dispatch($payload);

        $eventType = $payload['event_type'] ?? null;

        if (! $eventType) {
            return $this->successMethod('Webhook received');
        }

        $method = 'handle' . Str::studly(str_replace('.', '_', $eventType));

        if (method_exists($this, $method)) {
            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

        return $this->missingMethod($payload);
    }

    /**
     * Handle purchase.paid event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handlePurchasePaid(array $payload): Response
    {
        $purchase = $payload['purchase'] ?? [];
        $clientId = $purchase['client']['id'] ?? null;

        if (! $clientId) {
            return $this->successMethod('Webhook received');
        }

        $billable = $this->getBillableByChipId($clientId);

        if (! $billable) {
            return $this->successMethod('Webhook received');
        }

        // Update default payment method if recurring token provided
        $this->updatePaymentMethodFromWebhook($billable, $purchase);

        // Update subscription status if applicable
        $this->updateSubscriptionOnPaymentSuccess($billable, $purchase);

        PaymentSucceeded::dispatch($billable, $purchase);

        return $this->successMethod();
    }

    /**
     * Handle purchase.payment_failure event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handlePurchasePaymentFailure(array $payload): Response
    {
        $purchase = $payload['purchase'] ?? [];
        $clientId = $purchase['client']['id'] ?? null;

        if (! $clientId) {
            return $this->successMethod('Webhook received');
        }

        $billable = $this->getBillableByChipId($clientId);

        if (! $billable) {
            return $this->successMethod('Webhook received');
        }

        // Update subscription status to past due
        $this->updateSubscriptionOnPaymentFailure($billable, $purchase);

        PaymentFailed::dispatch($billable, $purchase);

        return $this->successMethod();
    }

    /**
     * Update payment method from webhook data.
     *
     * @param  array<string, mixed>  $purchase
     */
    protected function updatePaymentMethodFromWebhook(Model $billable, array $purchase): void
    {
        $recurringToken = $purchase['recurring_token'] ?? null;

        if (! $recurringToken) {
            return;
        }

        // Only update if no default payment method exists
        if ($billable->default_pm_id) {
            return;
        }

        $card = $purchase['card'] ?? [];

        $billable->forceFill([
            'default_pm_id' => $recurringToken,
            'pm_type' => $card['brand'] ?? null,
            'pm_last_four' => $card['last_4'] ?? null,
        ])->save();
    }

    /**
     * Update subscription status on payment success.
     *
     * @param  array<string, mixed>  $purchase
     *
     * @phpstan-param Model&BillableContract $billable
     */
    protected function updateSubscriptionOnPaymentSuccess(Model $billable, array $purchase): void
    {
        $subscriptionType = $purchase['metadata']['subscription_type'] ?? null;

        if (! $subscriptionType) {
            return;
        }

        $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

        if (! $subscription) {
            return;
        }

        $subscription->forceFill([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'next_billing_at' => $this->calculateNextBillingDate($subscription),
        ])->save();
    }

    /**
     * Update subscription status on payment failure.
     *
     * @param  array<string, mixed>  $purchase
     *
     * @phpstan-param Model&BillableContract $billable
     */
    protected function updateSubscriptionOnPaymentFailure(Model $billable, array $purchase): void
    {
        $subscriptionType = $purchase['metadata']['subscription_type'] ?? null;

        if (! $subscriptionType) {
            return;
        }

        $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

        if (! $subscription) {
            return;
        }

        $subscription->forceFill([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ])->save();
    }

    /**
     * Calculate the next billing date based on subscription interval.
     */
    protected function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        $interval = $subscription->billing_interval ?? 'month';
        $intervalCount = $subscription->billing_interval_count ?? 1;

        return match ($interval) {
            'day' => Carbon::now()->addDays($intervalCount),
            'week' => Carbon::now()->addWeeks($intervalCount),
            'month' => Carbon::now()->addMonths($intervalCount),
            'year' => Carbon::now()->addYears($intervalCount),
            default => Carbon::now()->addMonth(),
        };
    }

    /**
     * Get the billable instance by CHIP ID.
     *
     * @phpstan-return (Model&BillableContract)|null
     */
    protected function getBillableByChipId(?string $chipId): ?Model
    {
        if (! $chipId) {
            return null;
        }

        if ((bool) config('cashier-chip.features.owner.enabled', true)) {
            return Cashier::findBillableForWebhook($chipId);
        }

        return Cashier::findBillable($chipId);
    }

    /**
     * Verify webhook signatures when enabled.
     */
    protected function signatureFailureResponse(Request $request): ?Response
    {
        $shouldVerify = (bool) config('cashier-chip.webhooks.verify_signature', true);

        if (! $shouldVerify) {
            if (app()->environment('production')) {
                Log::channel(config('cashier-chip.logger', config('logging.default', 'stack')))
                    ->warning('cashier-chip webhook signature verification is disabled in production');
            }

            return null;
        }

        $signature = $request->header('X-Signature');

        if (! is_string($signature) || $signature === '') {
            return new Response('Missing signature header', 400);
        }

        $secret = config('cashier-chip.webhooks.secret');

        if (! is_string($secret) || $secret === '') {
            return new Response('Webhook secret not configured', 500);
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            return new Response('Invalid signature', 401);
        }

        return null;
    }

    /**
     * Handle successful calls on the controller.
     */
    protected function successMethod(string $message = 'Webhook Handled'): Response
    {
        return new Response($message, 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function missingMethod(array $parameters = []): Response
    {
        return new Response('Webhook received', 200);
    }
}
