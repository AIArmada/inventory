<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Policies;

use Illuminate\Database\Eloquent\Model;

/**
 * Policy for payment method authorization in the customer portal.
 *
 * Ensures users can only manage their own payment methods.
 */
class PaymentMethodPolicy
{
    /**
     * Determine whether the user can view any payment methods.
     */
    public function viewAny(Model $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the payment method.
     */
    public function view(Model $user, Model $paymentMethod): bool
    {
        return $this->ownsPaymentMethod($user, $paymentMethod);
    }

    /**
     * Determine whether the user can add a payment method.
     */
    public function create(Model $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the payment method.
     */
    public function update(Model $user, Model $paymentMethod): bool
    {
        return $this->ownsPaymentMethod($user, $paymentMethod);
    }

    /**
     * Determine whether the user can delete the payment method.
     */
    public function delete(Model $user, Model $paymentMethod): bool
    {
        return $this->ownsPaymentMethod($user, $paymentMethod);
    }

    /**
     * Determine whether the user can set the payment method as default.
     */
    public function setDefault(Model $user, Model $paymentMethod): bool
    {
        return $this->ownsPaymentMethod($user, $paymentMethod);
    }

    /**
     * Check if the user owns the payment method.
     */
    protected function ownsPaymentMethod(Model $user, Model $paymentMethod): bool
    {
        $userId = $user->getKey();
        $pmUserId = $paymentMethod->getAttribute('billable_id') ?? $paymentMethod->getAttribute('user_id');

        if ($userId === null || $pmUserId === null) {
            return false;
        }

        return (string) $userId === (string) $pmUserId;
    }
}
