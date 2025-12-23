<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Listeners;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to chip package BillingCancelled events and handles cashier-chip subscription logic.
 */
class HandleBillingCancelled
{
    public function handle(BillingCancelled $event): void
    {
        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            return;
        }

        $billingTemplateClient = $event->billingTemplateClient;

        $clientId = $billingTemplateClient->client_id;

        if ($clientId === '') {
            return;
        }

        /** @var (Model&BillableContract)|null $billable */
        $billable = (bool) config('cashier-chip.features.owner.enabled', true)
            ? Cashier::findBillableForWebhook($clientId)
            : Cashier::findBillable($clientId);

        if ($billable === null) {
            return;
        }

        $query = Subscription::query()
            ->where('user_id', $billable->getKey())
            ->where(function ($query) use ($billingTemplateClient): void {
                $query->where('chip_billing_template_id', $billingTemplateClient->billing_template_id)
                    ->orWhere('recurring_token', $billingTemplateClient->recurring_token);
            });

        $subscription = $query->first();

        if ($subscription) {
            $subscription->forceFill([
                'chip_status' => 'canceled',
                'ends_at' => now(),
            ])->save();

            SubscriptionCanceled::dispatch($subscription);
        }
    }
}
