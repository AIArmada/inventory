<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use AIArmada\Cashier\Exceptions\IncompletePayment;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * Contract for payment representations.
 */
interface PaymentContract extends Arrayable, Jsonable
{
    /**
     * Get the payment ID.
     */
    public function id(): string;

    /**
     * Get the gateway name.
     */
    public function gateway(): string;

    /**
     * Get the raw amount in cents.
     */
    public function rawAmount(): int;

    /**
     * Get the formatted amount.
     */
    public function amount(): string;

    /**
     * Get the currency.
     */
    public function currency(): string;

    /**
     * Get the payment status.
     */
    public function status(): string;

    /**
     * Get an optional gateway error code.
     */
    public function errorCode(): ?string;

    /**
     * Determine if the payment is pending.
     */
    public function isPending(): bool;

    /**
     * Determine if the payment succeeded.
     */
    public function isSucceeded(): bool;

    /**
     * Determine if the payment failed.
     */
    public function isFailed(): bool;

    /**
     * Determine if the payment is canceled.
     */
    public function isCanceled(): bool;

    /**
     * Determine if the payment requires action (e.g., 3DS).
     */
    public function requiresAction(): bool;

    /**
     * Determine if the payment requires redirect.
     */
    public function requiresRedirect(): bool;

    /**
     * Get the redirect URL if action is required.
     */
    public function redirectUrl(): ?string;

    /**
     * Get the receipt/invoice URL.
     */
    public function receiptUrl(): ?string;

    /**
     * Validate the payment (throws on failure).
     *
     * @throws IncompletePayment
     */
    public function validate(): self;

    /**
     * Get the underlying gateway payment object.
     */
    public function asGatewayPayment(): mixed;
}
