<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to chip package PurchasePaid events and handles cashier-chip billing logic.
 */
class HandlePurchasePaid
{
    public function handle(PurchasePaid $event): void
    {
        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            return;
        }

        $purchase = $event->purchase;
        $payload = $event->payload;

        $clientId = $purchase->getClientId();

        if ($clientId === null) {
            return;
        }

        /** @var Model|null $billable */
        $billable = (bool) config('cashier-chip.features.owner.enabled', true)
            ? Cashier::findBillableForWebhook($clientId)
            : Cashier::findBillable($clientId);

        if ($billable === null) {
            return;
        }

        // Dispatch cashier-chip payment succeeded event
        PaymentSucceeded::dispatch($billable, $purchase->toArray());

        // Handle recurring token if present
        if ($recurringToken = $purchase->recurring_token) {
            $this->handleRecurringToken($billable, $recurringToken, $purchase->toArray());
        }

        // Handle subscription charge if this is a subscription payment
        if ($subscriptionType = $this->getSubscriptionTypeFromPurchase($payload)) {
            $this->handleSubscriptionPayment($billable, $subscriptionType);
        }
    }

    /**
     * Handle recurring token from a purchase.
     *
     * @phpstan-ignore-next-line
     *
     * @param  array<string, mixed>  $purchase
     */
    protected function handleRecurringToken(object $billable, string $recurringToken, array $purchase): void
    {
        if (! $billable->hasDefaultPaymentMethod()) {
            $transactionData = $purchase['transaction_data'] ?? [];
            $extra = $transactionData['extra'] ?? [];
            $card = $purchase['card'] ?? [];

            $billable->forceFill([
                'default_pm_id' => $recurringToken,
                'pm_type' => $card['brand'] ?? $extra['card_brand'] ?? $transactionData['payment_method'] ?? 'card',
                'pm_last_four' => $card['last_4'] ?? $extra['card_last_4'] ?? null,
            ])->save();
        }
    }

    /**
     * Handle a subscription payment success.
     *
     * @phpstan-ignore-next-line
     */
    protected function handleSubscriptionPayment(object $billable, string $subscriptionType): void
    {
        if (! $billable instanceof Model) {
            return;
        }

        $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

        if ($subscription) {
            $interval = $subscription->billing_interval ?? 'month';
            $count = $subscription->billing_interval_count ?? 1;

            $subscription->forceFill([
                'chip_status' => 'active',
                'next_billing_at' => now()->add($interval, $count),
            ])->save();
        }
    }

    /**
     * Extract subscription type from purchase metadata or reference.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function getSubscriptionTypeFromPurchase(array $payload): ?string
    {
        $purchase = $payload['purchase'] ?? $payload;
        $metadata = $purchase['metadata'] ?? [];

        if (isset($metadata['subscription_type'])) {
            return $metadata['subscription_type'];
        }

        $reference = $purchase['reference'] ?? '';
        if (preg_match('/Subscription (\w+)/', $reference, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
