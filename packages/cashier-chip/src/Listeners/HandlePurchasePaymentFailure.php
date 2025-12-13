<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to chip package PurchasePaymentFailure events and handles cashier-chip billing logic.
 */
class HandlePurchasePaymentFailure
{
    public function handle(PurchasePaymentFailure $event): void
    {
        $purchase = $event->purchase;
        $payload = $event->payload;

        $clientId = $purchase->getClientId();

        if ($clientId === null) {
            return;
        }

        /** @var Model|null $billable */
        $billable = Cashier::findBillable($clientId);

        if ($billable === null) {
            return;
        }

        // Dispatch cashier-chip payment failed event
        PaymentFailed::dispatch($billable, $purchase->toArray());

        // Handle subscription payment failure
        if ($subscriptionType = $this->getSubscriptionTypeFromPurchase($payload)) {
            $this->handleSubscriptionPaymentFailure($billable, $subscriptionType);
        }
    }

    /**
     * Handle a subscription payment failure.
     *
     * @phpstan-ignore-next-line
     */
    protected function handleSubscriptionPaymentFailure(object $billable, string $subscriptionType): void
    {
        $subscription = $billable->subscription($subscriptionType);

        if ($subscription) {
            $subscription->forceFill([
                'chip_status' => 'past_due',
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
