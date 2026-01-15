<?php

declare(strict_types=1);

namespace AIArmada\Cashier;

use AIArmada\Cashier\Concerns\ManagesGateway;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Unified Billable trait for multi-gateway payment support.
 *
 * This trait provides a unified interface for interacting with multiple
 * payment gateways (Stripe, CHIP, etc.) through a single API.
 *
 * IMPORTANT: This trait should be used ALONGSIDE the gateway-specific traits:
 * - \Laravel\Cashier\Billable for Stripe
 * - \AIArmada\CashierChip\Billable for CHIP
 *
 * Add this trait to your User model (or any billable model):
 *
 * ```php
 * use AIArmada\Cashier\Billable as CashierBillable;
 * use Laravel\Cashier\Billable as StripeBillable;
 * use AIArmada\CashierChip\Billable as ChipBillable;
 *
 * class User extends Authenticatable
 * {
 *     use StripeBillable, ChipBillable, CashierBillable;
 * }
 * ```
 *
 * Then you can use the unified API:
 *
 * ```php
 * // Use default gateway
 * $user->newGatewaySubscription('default', 'price_xxx')->create();
 *
 * // Use specific gateway
 * $user->gateway('chip')->subscription($user, 'default', 'price_xxx')->create();
 *
 * // Get all subscriptions across gateways
 * $user->allSubscriptions();
 * ```
 */
trait Billable // @phpstan-ignore trait.unused
{
    use ManagesGateway;

    /**
     * Get all subscriptions across all gateways.
     *
     * @return Collection<int, SubscriptionContract>
     */
    public function allSubscriptions(): Collection
    {
        $subscriptions = collect();

        foreach (Cashier::availableGateways() as $gateway) {
            try {
                $gatewaySubscriptions = $this->gateway($gateway)->subscriptions($this);
                $subscriptions = $subscriptions->merge($gatewaySubscriptions);
            } catch (Throwable) {
                // Gateway not available, skip
            }
        }

        return $subscriptions->sortByDesc(fn ($sub) => $sub->createdAt());
    }

    /**
     * Get a subscription by type from any gateway.
     */
    public function findSubscription(string $type = 'default'): ?SubscriptionContract
    {
        foreach (Cashier::availableGateways() as $gateway) {
            try {
                $subscription = $this->gatewaySubscription($type, $gateway);
                if ($subscription) {
                    return $subscription;
                }
            } catch (Throwable) {
                // Gateway not available, skip
            }
        }

        return null;
    }

    /**
     * Determine if the billable is subscribed to a given type on any gateway.
     */
    public function subscribedOnAny(string $type = 'default', ?string $price = null): bool
    {
        foreach (Cashier::availableGateways() as $gateway) {
            if ($this->subscribedViaGateway($type, $price, $gateway)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if on trial on any gateway.
     */
    public function onTrialOnAny(string $type = 'default'): bool
    {
        foreach (Cashier::availableGateways() as $gateway) {
            try {
                $subscription = $this->gatewaySubscription($type, $gateway);
                if ($subscription && $subscription->onTrial()) {
                    return true;
                }
            } catch (Throwable) {
                // Gateway not available, skip
            }
        }

        return false;
    }

    /**
     * Check if on a generic trial (on the billable, not subscription).
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }
}
