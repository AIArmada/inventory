<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use Exception;
use InvalidArgumentException;
use Laravel\Cashier\PaymentMethod;

/**
 * Wrapper for Stripe payment method.
 */
class StripePaymentMethod implements PaymentMethodContract
{
    protected PaymentMethod $paymentMethod;

    /**
     * Create a new Stripe payment method wrapper.
     */
    public function __construct(mixed $paymentMethod, protected ?BillableContract $billable = null)
    {
        if (! $paymentMethod instanceof PaymentMethod) {
            throw new InvalidArgumentException('StripePaymentMethod expects an instance of ' . PaymentMethod::class);
        }

        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Get the payment method ID.
     */
    public function id(): string
    {
        return $this->paymentMethod->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'stripe';
    }

    /**
     * Get the card brand.
     */
    public function brand(): ?string
    {
        return $this->paymentMethod->card?->brand;
    }

    /**
     * Get the last four digits.
     */
    public function lastFour(): ?string
    {
        return $this->paymentMethod->card?->last4;
    }

    /**
     * Get the expiration month.
     */
    public function expirationMonth(): ?int
    {
        return $this->paymentMethod->card?->exp_month;
    }

    /**
     * Get the expiration year.
     */
    public function expirationYear(): ?int
    {
        return $this->paymentMethod->card?->exp_year;
    }

    /**
     * Get the payment method type.
     */
    public function type(): string
    {
        return $this->paymentMethod->type;
    }

    /**
     * Determine if this is the default payment method.
     */
    public function isDefault(): bool
    {
        if (! $this->billable) {
            return false;
        }

        $default = $this->billable->defaultPaymentMethod();

        if ($default instanceof PaymentMethod) {
            return $default->id === $this->id();
        }

        if ($default instanceof PaymentMethodContract) {
            return $default->id() === $this->id();
        }

        return false;
    }

    /**
     * Get the owner.
     */
    public function owner(): ?BillableContract
    {
        return $this->billable;
    }

    /**
     * Delete the payment method.
     *
     * @throws Exception
     */
    public function delete(): void
    {
        $this->paymentMethod->delete();
    }

    /**
     * Get the underlying payment method.
     */
    public function asGatewayPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
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
            'type' => $this->type(),
            'brand' => $this->brand(),
            'last_four' => $this->lastFour(),
            'expiration_month' => $this->expirationMonth(),
            'expiration_year' => $this->expirationYear(),
            'is_default' => $this->isDefault(),
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '{}';
    }
}
