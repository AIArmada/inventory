<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\CashierChip\Payment;
use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Http\RedirectResponse;

/**
 * Wrapper for CHIP payment (purchase).
 */
class ChipPayment implements PaymentContract
{
    /**
     * The payment instance.
     */
    protected Payment | PurchaseData $payment;

    /**
     * Create a new CHIP payment wrapper.
     */
    public function __construct(Payment | PurchaseData $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Get the payment ID.
     */
    public function id(): string
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->id() ?? '';
        }

        return $this->payment->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
    }

    /**
     * Determine if the payment is pending.
     */
    public function isPending(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->isPending();
        }

        // CHIP pending statuses
        return in_array($this->payment->status, [
            'created',
            'pending_execute',
            'pending_capture',
            'pending_charge',
            'pending_refund',
            'pending_release',
        ]);
    }

    /**
     * Determine if the payment succeeded.
     */
    public function isSucceeded(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->isSucceeded();
        }

        // CHIP paid statuses
        return in_array($this->payment->status, ['paid', 'cleared', 'settled']);
    }

    /**
     * Determine if the payment failed.
     */
    public function isFailed(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->isFailed();
        }

        // CHIP failure statuses
        return in_array($this->payment->status, ['error', 'blocked']);
    }

    /**
     * Determine if the payment is expired.
     */
    public function isExpired(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->isExpired();
        }

        return $this->payment->status === 'expired';
    }

    /**
     * Determine if the payment was cancelled.
     */
    public function isCancelled(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->isCancelled();
        }

        return $this->payment->status === 'cancelled';
    }

    /**
     * Determine if the payment is canceled (alias for isCancelled).
     */
    public function isCanceled(): bool
    {
        return $this->isCancelled();
    }

    /**
     * Determine if the payment was refunded.
     */
    public function isRefunded(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->isRefunded();
        }

        return $this->payment->status === 'refunded';
    }

    /**
     * Determine if the payment is on hold.
     */
    public function isOnHold(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->status() === 'hold';
        }

        return $this->payment->status === 'hold';
    }

    /**
     * Determine if the payment is preauthorized.
     */
    public function isPreauthorized(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->status() === 'preauthorized';
        }

        return $this->payment->status === 'preauthorized';
    }

    /**
     * Determine if the payment requires action (e.g., 3DS).
     */
    public function requiresAction(): bool
    {
        // CHIP handles 3DS internally via the checkout URL redirect
        return $this->requiresRedirect();
    }

    /**
     * Determine if the payment requires redirect.
     */
    public function requiresRedirect(): bool
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->requiresRedirect();
        }

        // Requires redirect if created/pending and has checkout URL
        return in_array($this->payment->status, ['created', 'viewed'])
            && ! empty($this->payment->checkout_url);
    }

    /**
     * Get the redirect URL if required.
     */
    public function redirectUrl(): ?string
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->checkoutUrl();
        }

        return $this->payment->checkout_url;
    }

    /**
     * Get the receipt/invoice URL.
     */
    public function receiptUrl(): ?string
    {
        if ($this->payment instanceof Payment) {
            $purchase = $this->payment->asChipPurchase();

            return $purchase->invoice_url ?? null;
        }

        return $this->payment->invoice_url;
    }

    /**
     * Redirect to complete the payment.
     */
    public function redirect(): RedirectResponse
    {
        return redirect()->to($this->redirectUrl());
    }

    /**
     * Get the currency.
     */
    public function currency(): string
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->currency();
        }

        return $this->payment->purchase->currency ?? 'MYR';
    }

    /**
     * Get the formatted amount.
     */
    public function amount(): string
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->amount();
        }

        return number_format($this->rawAmount() / 100, 2) . ' ' . $this->currency();
    }

    /**
     * Get the raw amount in cents.
     */
    public function rawAmount(): int
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->rawAmount();
        }

        // CHIP returns amount in decimal, convert to cents
        $amount = $this->payment->purchase->getTotalInCents();

        return (int) $amount;
    }

    /**
     * Get the payment status.
     */
    public function status(): string
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->status() ?? 'unknown';
        }

        return $this->payment->status ?? 'unknown';
    }

    /**
     * Validate the payment and throw exception if failed.
     *
     * @throws \AIArmada\CashierChip\Exceptions\IncompletePayment
     */
    public function validate(): static
    {
        if ($this->payment instanceof Payment) {
            $this->payment->validate();
        }

        return $this;
    }

    /**
     * Get the recurring token from this purchase.
     */
    public function recurringToken(): ?string
    {
        if ($this->payment instanceof Payment) {
            return $this->payment->recurringToken();
        }

        return $this->payment->recurring_token;
    }

    /**
     * Get the underlying payment.
     */
    public function asGatewayPayment(): Payment | PurchaseData
    {
        return $this->payment;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway' => $this->gateway(),
            'status' => $this->status(),
            'currency' => $this->currency(),
            'amount' => $this->amount(),
            'raw_amount' => $this->rawAmount(),
            'is_succeeded' => $this->isSucceeded(),
            'is_pending' => $this->isPending(),
            'is_failed' => $this->isFailed(),
            'requires_redirect' => $this->requiresRedirect(),
            'recurring_token' => $this->recurringToken(),
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
