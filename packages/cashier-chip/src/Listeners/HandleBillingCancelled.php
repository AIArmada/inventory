<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Events\BillingCancelled;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to chip package BillingCancelled events and handles cashier-chip subscription logic.
 */
class HandleBillingCancelled
{
    public function handle(BillingCancelled $event): void
    {
        $billingTemplateClient = $event->billingTemplateClient;

        $clientId = $billingTemplateClient->client_id;

        if ($clientId === '') {
            return;
        }

        /** @var (Model&BillableContract)|null $billable */
        $billable = Cashier::findBillable($clientId);

        if ($billable === null) {
            return;
        }

        // Find subscription by billing template ID or recurring token
        /** @var Subscription|null $subscription */
        $subscription = $billable->subscriptions()
            ->where('chip_billing_template_id', $billingTemplateClient->billing_template_id)
            ->orWhere('recurring_token', $billingTemplateClient->recurring_token)
            ->first();

        if ($subscription) {
            $subscription->forceFill([
                'chip_status' => 'canceled',
                'ends_at' => now(),
            ])->save();

            SubscriptionCanceled::dispatch($subscription);
        }
    }
}
