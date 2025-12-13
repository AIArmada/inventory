<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\CashierChip\PaymentMethod as ChipNativePaymentMethod;
use Exception;
use InvalidArgumentException;

/**
 * Wrapper for CHIP payment method (recurring token).
 */
class ChipPaymentMethod implements PaymentMethodContract
{
    /** @var array<string, mixed> */
    protected array $token;

    /**
     * Create a new CHIP payment method wrapper.
     */
    public function __construct(mixed $token, protected ?BillableContract $billable = null)
    {
        if ($token instanceof ChipNativePaymentMethod) {
            $token = $token->toArray();
        }

        if (! is_array($token)) {
            throw new InvalidArgumentException('ChipPaymentMethod expects an array token or ' . ChipNativePaymentMethod::class);
        }

        /** @var array<string, mixed> $token */
        $this->token = $token;
    }

    /**
     * Get the payment method ID (recurring token).
     */
    public function id(): string
    {
        return $this->token['recurring_token'] ?? $this->token['id'] ?? '';
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
    }

    /**
     * Get the card brand.
     */
    public function brand(): ?string
    {
        return $this->token['card_brand'] ?? $this->token['brand'] ?? null;
    }

    /**
     * Get the last four digits.
     */
    public function lastFour(): ?string
    {
        return $this->token['card_last4'] ?? $this->token['last4'] ?? null;
    }

    /**
     * Get the expiration month.
     */
    public function expirationMonth(): ?int
    {
        $exp = $this->token['card_expiry'] ?? null;
        if ($exp && preg_match('/^(\d{2})\/\d{2}$/', $exp, $matches)) {
            return (int) $matches[1];
        }

        return $this->token['exp_month'] ?? null;
    }

    /**
     * Get the expiration year.
     */
    public function expirationYear(): ?int
    {
        $exp = $this->token['card_expiry'] ?? null;
        if ($exp && preg_match('/^\d{2}\/(\d{2})$/', $exp, $matches)) {
            $year = (int) $matches[1];

            return $year + 2000; // Convert 2-digit to 4-digit year
        }

        return $this->token['exp_year'] ?? null;
    }

    /**
     * Get the payment method type.
     */
    public function type(): string
    {
        return $this->token['payment_method'] ?? $this->token['type'] ?? 'card';
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

        if (is_string($default)) {
            return $default === $this->id();
        }

        if (is_array($default)) {
            return ($default['recurring_token'] ?? $default['id'] ?? null) === $this->id();
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
        if (! $this->billable) {
            throw new Exception('Cannot delete payment method without billable owner');
        }

        $this->billable->deletePaymentMethod($this->id());
    }

    /**
     * Get the underlying payment method data.
     *
     * @return array<string, mixed>
     */
    public function asGatewayPaymentMethod(): array
    {
        return $this->token;
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
