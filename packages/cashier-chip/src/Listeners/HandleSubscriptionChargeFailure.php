<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to chip package PurchaseSubscriptionChargeFailure events.
 */
class HandleSubscriptionChargeFailure
{
    public function handle(PurchaseSubscriptionChargeFailure $event): void
    {
        $purchase = $event->purchase;
        $payload = $event->payload;

        $clientId = $purchase->getClientId();

        if ($clientId === null) {
            return;
        }

        /** @var (Model&BillableContract)|null $billable */
        $billable = Cashier::findBillable($clientId);

        if ($billable === null) {
            return;
        }

        $subscriptionType = $this->getSubscriptionTypeFromPurchase($payload);

        if ($subscriptionType === null) {
            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = $billable->subscription($subscriptionType);

        if ($subscription) {
            $subscription->forceFill([
                'chip_status' => 'past_due',
            ])->save();

            $reason = $purchase->failure_reason ?? 'Subscription charge failed';

            SubscriptionRenewalFailed::dispatch($subscription, $reason);
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
