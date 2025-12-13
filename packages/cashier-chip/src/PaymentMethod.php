<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Contracts\BillableContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use ReturnTypeWillChange;

/**
 * CHIP Payment Method (Recurring Token) wrapper class.
 *
 * CHIP uses "Recurring Token" for saved payment methods,
 * similar to Stripe's PaymentMethod.
 */
class PaymentMethod implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The owner of the payment method.
     *
     * @phpstan-var Model&BillableContract
     */
    protected $owner;

    /**
     * The CHIP recurring token data.
     */
    protected array $recurringToken;

    /**
     * Create a new PaymentMethod instance.
     *
     * @param  array  $recurringToken  The CHIP recurring token data
     * @return void
     */
    /**
     * @phpstan-param Model&BillableContract $owner
     */
    public function __construct(Model $owner, array $recurringToken)
    {
        $this->owner = $owner;
        $this->recurringToken = $recurringToken;
    }

    /**
     * Dynamically get values from the recurring token data.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->recurringToken[$key] ?? null;
    }

    /**
     * Get the recurring token ID.
     */
    public function id(): ?string
    {
        return $this->recurringToken['id'] ?? $this->recurringToken['recurring_token'] ?? null;
    }

    /**
     * Get the card brand (if available).
     */
    public function brand(): ?string
    {
        return $this->recurringToken['card_brand'] ?? $this->recurringToken['brand'] ?? null;
    }

    /**
     * Get the last four digits of the card (if available).
     */
    public function lastFour(): ?string
    {
        return $this->recurringToken['last_4'] ?? $this->recurringToken['card_last_4'] ?? null;
    }

    /**
     * Get the expiration month (if available).
     */
    public function expirationMonth(): ?int
    {
        return $this->recurringToken['exp_month'] ?? null;
    }

    /**
     * Get the expiration year (if available).
     */
    public function expirationYear(): ?int
    {
        return $this->recurringToken['exp_year'] ?? null;
    }

    /**
     * Get the card brand for blade templates (alias for brand).
     */
    public function cardBrand(): ?string
    {
        return $this->brand();
    }

    /**
     * Get the last four digits for blade templates (alias for lastFour).
     */
    public function cardLastFour(): ?string
    {
        return $this->lastFour();
    }

    /**
     * Get the expiration month for blade templates (alias for expirationMonth).
     */
    public function cardExpMonth(): ?int
    {
        return $this->expirationMonth();
    }

    /**
     * Get the expiration year for blade templates (alias for expirationYear).
     */
    public function cardExpYear(): ?int
    {
        return $this->expirationYear();
    }

    /**
     * Get the CHIP token identifier (for blade template compatibility).
     */
    public function chipToken(): ?string
    {
        return $this->id();
    }

    /**
     * Get the type of payment method.
     */
    public function type(): string
    {
        return $this->recurringToken['type'] ?? 'card';
    }

    /**
     * Determine if this is the default payment method.
     */
    public function isDefault(): bool
    {
        $defaultMethod = $this->owner->defaultPaymentMethod();

        return $defaultMethod instanceof self && $this->id() === $defaultMethod->id();
    }

    /**
     * Delete the payment method.
     */
    public function delete(): void
    {
        $this->owner->deletePaymentMethod($this->id());
    }

    /**
     * Get the Eloquent model instance.
     *
     * @return Model
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the underlying recurring token data.
     */
    public function asChipRecurringToken(): array
    {
        return $this->recurringToken;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->recurringToken;
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
