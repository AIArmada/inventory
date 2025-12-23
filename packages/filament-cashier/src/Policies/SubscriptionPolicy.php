<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Policies;

use Illuminate\Database\Eloquent\Model;

/**
 * Policy for subscription authorization in the customer portal.
 *
 * Ensures users can only manage their own subscriptions.
 */
class SubscriptionPolicy
{
    /**
     * Determine whether the user can view any subscriptions.
     * In the customer portal, users can only view their own.
     */
    public function viewAny(Model $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the subscription.
     */
    public function view(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Determine whether the user can cancel the subscription.
     */
    public function cancel(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Determine whether the user can resume the subscription.
     */
    public function resume(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Determine whether the user can update the subscription.
     */
    public function update(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Determine whether the user can swap the subscription plan.
     */
    public function swap(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Check if the user owns the subscription.
     */
    protected function ownsSubscription(Model $user, Model $subscription): bool
    {
        $userId = $user->getKey();
        $subscriptionUserId = $subscription->getAttribute('user_id') ?? $subscription->getAttribute('billable_id');

        if ($userId === null || $subscriptionUserId === null) {
            return false;
        }

        return (string) $userId === (string) $subscriptionUserId;
    }
}
