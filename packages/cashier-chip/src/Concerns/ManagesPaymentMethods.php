<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\PaymentMethod;
use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Support\Collection;
use SensitiveParameter;
use Throwable;

trait ManagesPaymentMethods // @phpstan-ignore trait.unused
{
    /**
     * Get the customer's recurring tokens (payment methods).
     *
     * @return Collection<int, PaymentMethod>
     */
    public function paymentMethods(): Collection
    {
        if (! $this->hasChipId()) {
            return collect();
        }

        $tokens = Cashier::chip()->listClientRecurringTokens($this->chip_id);

        if (isset($tokens['results']) && is_array($tokens['results'])) {
            $tokens = $tokens['results'];
        }

        return collect($tokens)->map(function ($token) {
            return new PaymentMethod($this, $token);
        });
    }

    /**
     * Get a specific recurring token by ID.
     */
    public function findPaymentMethod(#[SensitiveParameter] string $paymentMethodId): ?PaymentMethod
    {
        if (! $this->hasChipId()) {
            return null;
        }

        try {
            $token = Cashier::chip()->getClientRecurringToken($this->chip_id, $paymentMethodId);

            return new PaymentMethod($this, $token);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Determine if the customer has a default payment method.
     */
    public function hasDefaultPaymentMethod(): bool
    {
        return ! is_null($this->pm_type);
    }

    /**
     * Determine if the customer has any payment method.
     */
    public function hasPaymentMethod(): bool
    {
        return $this->paymentMethods()->isNotEmpty();
    }

    /**
     * Get the default payment method for the customer.
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        if (! $this->hasDefaultPaymentMethod()) {
            return null;
        }

        return $this->paymentMethods()->first();
    }

    /**
     * Update the default payment method for the customer.
     */
    public function updateDefaultPaymentMethod(#[SensitiveParameter] string $paymentMethodId): self
    {
        $paymentMethod = $this->findPaymentMethod($paymentMethodId);

        if ($paymentMethod) {
            $this->forceFill([
                'pm_type' => $paymentMethod->type(),
                'pm_last_four' => $paymentMethod->lastFour(),
            ])->save();
        }

        return $this;
    }

    /**
     * Update default payment method from CHIP.
     */
    public function updateDefaultPaymentMethodFromChip(): self
    {
        $defaultMethod = $this->paymentMethods()->first();

        if ($defaultMethod) {
            $this->forceFill([
                'pm_type' => $defaultMethod->type(),
                'pm_last_four' => $defaultMethod->lastFour(),
            ])->save();
        } else {
            $this->forceFill([
                'pm_type' => null,
                'pm_last_four' => null,
            ])->save();
        }

        return $this;
    }

    /**
     * Delete a payment method from the customer.
     */
    public function deletePaymentMethod(#[SensitiveParameter] string $paymentMethodId): void
    {
        if (! $this->hasChipId()) {
            return;
        }

        Cashier::chip()->deleteClientRecurringToken($this->chip_id, $paymentMethodId);

        // If this was the default, update it
        if ($this->hasDefaultPaymentMethod()) {
            $this->updateDefaultPaymentMethodFromChip();
        }
    }

    /**
     * Delete all payment methods from the customer.
     */
    public function deletePaymentMethods(): void
    {
        foreach ($this->paymentMethods() as $paymentMethod) {
            $paymentMethod->delete();
        }

        $this->forceFill([
            'pm_type' => null,
            'pm_last_four' => null,
        ])->save();
    }

    /**
     * Create a setup purchase for adding payment methods.
     *
     * This creates a zero-amount preauthorization purchase that:
     * - Uses skip_capture=true to preauthorize without capturing
     * - Uses total_override=0 for zero-amount authorization
     * - Uses force_recurring=true to ensure a recurring token is saved
     *
     * On successful preauthorization, the webhook will receive purchase.preauthorized
     * event with the recurring_token that can be used for future charges.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupPurchase(array $options = []): PurchaseData
    {
        // Ensure customer exists on CHIP - create if not already exists
        if (! $this->hasChipId()) {
            $this->createAsChipCustomer();
        }

        $purchaseData = array_merge([
            'client_id' => $this->chip_id,
            'send_receipt' => false,
            'skip_capture' => true,
            'total_override' => 0,
            'force_recurring' => true,
            'purchase' => [
                'currency' => config('cashier-chip.currency', 'MYR'),
                'products' => [
                    [
                        'name' => $options['product_name'] ?? 'Payment Method Setup',
                        'price' => 0,
                        'quantity' => 1,
                    ],
                ],
            ],
            'brand_id' => config('chip.collect.brand_id'),
            'success_callback' => $options['success_url'] ?? null,
            'failure_callback' => $options['cancel_url'] ?? null,
            'success_redirect' => $options['success_url'] ?? null,
            'failure_redirect' => $options['cancel_url'] ?? null,
        ], $options['chip'] ?? []);

        return Cashier::chip()->createPurchase($purchaseData);
    }

    /**
     * Get the checkout URL for setting up a payment method.
     *
     * @param  array<string, mixed>  $options
     */
    public function setupPaymentMethodUrl(array $options = []): string
    {
        $purchase = $this->createSetupPurchase($options);

        return $purchase->checkout_url ?? '';
    }
}
