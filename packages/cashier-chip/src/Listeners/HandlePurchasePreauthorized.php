<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Cashier;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to chip package PurchasePreauthorized events.
 * Handles saving recurring tokens when a card is preauthorized without a charge.
 */
class HandlePurchasePreauthorized
{
    public function handle(PurchasePreauthorized $event): void
    {
        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            return;
        }

        $purchase = $event->purchase;

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

        $recurringToken = $purchase->recurring_token;

        if ($recurringToken === null) {
            return;
        }

        // Save the recurring token for future use
        $this->handleRecurringToken($billable, $recurringToken, $purchase->toArray());
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
}
