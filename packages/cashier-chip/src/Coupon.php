<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * Coupon wrapper for CHIP/Vouchers integration.
 *
 * Provides Stripe-compatible API while wrapping VoucherData internally.
 *
 * @implements Arrayable<string, mixed>
 */
class Coupon implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Create a new Coupon instance.
     */
    public function __construct(protected VoucherData $voucher) {}

    /**
     * Dynamically get values from the voucher.
     */
    public function __get(string $key): mixed
    {
        return match ($key) {
            'id' => $this->id(),
            'name' => $this->name(),
            'percent_off' => $this->percentOff(),
            'amount_off' => $this->rawAmountOff(),
            'currency' => $this->currency(),
            'duration' => $this->duration(),
            default => $this->voucher->{$key} ?? null,
        };
    }

    /**
     * Get the coupon ID (voucher code).
     */
    public function id(): string
    {
        return $this->voucher->code;
    }

    /**
     * Get the readable name for the Coupon.
     */
    public function name(): string
    {
        return $this->voucher->name ?: $this->voucher->code;
    }

    /**
     * Determine if the coupon is a percentage discount.
     */
    public function isPercentage(): bool
    {
        return $this->voucher->type === VoucherType::Percentage;
    }

    /**
     * Get the discount percentage for the coupon.
     *
     * Note: Voucher stores percentage in basis points (1000 = 10.00%).
     */
    public function percentOff(): ?float
    {
        if (! $this->isPercentage()) {
            return null;
        }

        // Convert from basis points to percentage
        return $this->voucher->value / 100;
    }

    /**
     * Get the amount off for the coupon (formatted).
     */
    public function amountOff(): ?string
    {
        if ($this->isPercentage() || $this->voucher->type === VoucherType::FreeShipping) {
            return null;
        }

        return $this->formatAmount($this->rawAmountOff() ?? 0);
    }

    /**
     * Get the raw amount off for the coupon (in smallest currency unit).
     */
    public function rawAmountOff(): ?int
    {
        if ($this->isPercentage() || $this->voucher->type === VoucherType::FreeShipping) {
            return null;
        }

        return (int) $this->voucher->value;
    }

    /**
     * Determine if this is an amount_off coupon with forever duration.
     *
     * Note: Vouchers use metadata.duration for duration control.
     */
    public function isForeverAmountOff(): bool
    {
        if ($this->isPercentage()) {
            return false;
        }

        return $this->duration() === 'forever';
    }

    /**
     * Get the duration of the coupon.
     *
     * @return string 'once', 'repeating', or 'forever'
     */
    public function duration(): string
    {
        $duration = $this->voucher->metadata['duration'] ?? 'once';

        if (! is_string($duration)) {
            return 'once';
        }

        return match ($duration) {
            'once', 'repeating', 'forever' => $duration,
            default => 'once',
        };
    }

    /**
     * Get the duration in months (for repeating coupons).
     */
    public function durationInMonths(): ?int
    {
        if ($this->duration() !== 'repeating') {
            return null;
        }

        $months = $this->voucher->metadata['duration_in_months'] ?? null;

        if ($months === null) {
            return null;
        }

        $months = (int) $months;

        return $months > 0 ? $months : null;
    }

    /**
     * Determine if the coupon is valid (active, started, not expired).
     */
    public function isValid(): bool
    {
        // Check status
        if ($this->voucher->status !== VoucherStatus::Active) {
            return false;
        }

        // Check if started
        if ($this->voucher->startsAt && Carbon::instance($this->voucher->startsAt)->isFuture()) {
            return false;
        }

        // Check if expired
        if ($this->voucher->expiresAt && Carbon::instance($this->voucher->expiresAt)->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the coupon is active.
     */
    public function isActive(): bool
    {
        return $this->voucher->status === VoucherStatus::Active;
    }

    /**
     * Determine if the coupon has expired.
     */
    public function isExpired(): bool
    {
        return $this->voucher->expiresAt && Carbon::instance($this->voucher->expiresAt)->isPast();
    }

    /**
     * Get the maximum discount amount (if capped).
     */
    public function maxDiscount(): ?int
    {
        return $this->voucher->maxDiscount ? (int) $this->voucher->maxDiscount : null;
    }

    /**
     * Get the minimum cart/order value required.
     */
    public function minCartValue(): ?int
    {
        return $this->voucher->minCartValue ? (int) $this->voucher->minCartValue : null;
    }

    /**
     * Calculate the discount for a given amount.
     */
    public function calculateDiscount(int $amount): int
    {
        if ($this->voucher->type === VoucherType::FreeShipping) {
            return 0;
        }

        // Check minimum cart value
        if ($this->minCartValue() && $amount < $this->minCartValue()) {
            return 0;
        }

        $discount = 0;

        if ($this->voucher->type === VoucherType::Percentage) {
            $discount = (int) ($amount * ($this->voucher->value / 10000));
        }

        if ($this->voucher->type === VoucherType::Fixed) {
            $discount = (int) $this->voucher->value;
        }

        // Apply max discount cap
        if ($this->maxDiscount() !== null) {
            $discount = min($discount, $this->maxDiscount());
        }

        // Discount cannot exceed amount
        return min($discount, $amount);
    }

    /**
     * Get the currency for this coupon.
     */
    public function currency(): string
    {
        return $this->voucher->currency;
    }

    /**
     * Get the underlying VoucherData instance.
     */
    public function asVoucherData(): VoucherData
    {
        return $this->voucher;
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'percent_off' => $this->percentOff(),
            'amount_off' => $this->rawAmountOff(),
            'currency' => $this->currency(),
            'duration' => $this->duration(),
            'duration_in_months' => $this->durationInMonths(),
            'max_discount' => $this->maxDiscount(),
            'min_cart_value' => $this->minCartValue(),
            'valid' => $this->isValid(),
        ];
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options) ?: '';
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Format the given amount into a displayable currency.
     */
    protected function formatAmount(int $amount): string
    {
        return Cashier::formatAmount($amount, $this->currency());
    }
}
