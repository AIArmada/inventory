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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a CHIP webhook call.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->all();

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

        /** @var Subscription|null $subscription */
        $subscription = $billable->subscription($subscriptionType);

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

        /** @var Subscription|null $subscription */
        $subscription = $billable->subscription($subscriptionType);

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

        return Cashier::findBillable($chipId);
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
