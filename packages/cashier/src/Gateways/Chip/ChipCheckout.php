<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Http\RedirectResponse;

/**
 * Wrapper for CHIP checkout session (purchase).
 */
class ChipCheckout implements CheckoutContract
{
    /**
     * Create a new CHIP checkout wrapper.
     */
    public function __construct(
        protected PurchaseData $purchase
    ) {}

    /**
     * Get the checkout session ID.
     */
    public function id(): string
    {
        return $this->purchase->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
    }

    /**
     * Get the checkout URL.
     */
    public function url(): string
    {
        return $this->purchase->getCheckoutUrl() ?? '';
    }

    /**
     * Redirect to the checkout page.
     */
    public function redirect(): RedirectResponse
    {
        return redirect()->to($this->url());
    }

    /**
     * Get the success URL.
     */
    public function successUrl(): string
    {
        return $this->purchase->success_callback ?? '';
    }

    /**
     * Get the cancel URL.
     */
    public function cancelUrl(): string
    {
        return $this->purchase->cancel_redirect ?? $this->purchase->failure_redirect ?? '';
    }

    /**
     * Get the checkout status.
     */
    public function status(): string
    {
        return $this->purchase->status ?? 'pending';
    }

    /**
     * Get the payment status.
     */
    public function paymentStatus(): string
    {
        return match ($this->purchase->status) {
            'paid', 'cleared', 'settled' => 'paid',
            'created', 'sent', 'viewed', 'pending_execute', 'pending_capture', 'pending_charge' => 'unpaid',
            'expired', 'overdue' => 'expired',
            'cancelled' => 'cancelled',
            'error', 'blocked' => 'failed',
            'refunded', 'pending_refund' => 'refunded',
            'hold', 'preauthorized' => 'authorized',
            default => 'unpaid',
        };
    }

    /**
     * Determine if the checkout is complete.
     */
    public function isComplete(): bool
    {
        return in_array($this->purchase->status, ['paid', 'cleared', 'settled']);
    }

    /**
     * Determine if the checkout was successful.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->purchase->status, ['paid', 'cleared', 'settled']);
    }

    /**
     * Determine if the checkout is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->purchase->status, [
            'created', 'sent', 'viewed',
            'pending_execute', 'pending_capture', 'pending_charge',
        ]);
    }

    /**
     * Determine if the checkout has expired.
     */
    public function isExpired(): bool
    {
        return in_array($this->purchase->status, ['expired', 'overdue']);
    }

    /**
     * Get the total amount in cents.
     */
    public function rawTotal(): int
    {
        // Use the Purchase's nested PurchaseDetails object
        return $this->purchase->purchase->getTotalInCents();
    }

    /**
     * Get the formatted total.
     */
    public function total(): string
    {
        return number_format($this->rawTotal() / 100, 2) . ' ' . mb_strtoupper($this->currency());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        return mb_strtoupper($this->purchase->purchase->currency);
    }

    /**
     * Get the customer if available.
     */
    public function customer(): ?CustomerContract
    {
        // Would need to resolve from CHIP
        return null;
    }

    /**
     * Get the recurring token from this purchase (if available).
     */
    public function recurringToken(): ?string
    {
        return $this->purchase->recurring_token;
    }

    /**
     * Get the underlying gateway checkout object.
     */
    public function asGatewayCheckout(): PurchaseData
    {
        return $this->purchase;
    }
}
