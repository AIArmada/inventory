<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Voucher data transfer object.
 *
 * Represents a voucher with all its properties and configuration.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class VoucherData extends Data
{
    /**
     * @param  array<string, mixed>|null  $valueConfig  Configuration for compound voucher types
     * @param  array<string, mixed>|null  $targetDefinition  Target definition for condition application
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly VoucherType $type,
        public readonly float $value,
        public readonly ?array $valueConfig,
        public readonly ?string $creditDestination,
        public readonly int $creditDelayHours,
        public readonly string $currency,
        public readonly ?float $minCartValue,
        public readonly ?float $maxDiscount,
        public readonly ?int $usageLimit,
        public readonly ?int $usageLimitPerUser,
        public readonly bool $allowsManualRedemption,
        public readonly int|string|null $ownerId,
        public readonly ?string $ownerType,
        #[WithCast(DateTimeInterfaceCast::class)]
        public readonly ?DateTimeInterface $startsAt,
        #[WithCast(DateTimeInterfaceCast::class)]
        public readonly ?DateTimeInterface $expiresAt,
        public readonly VoucherStatus $status,
        public readonly ?array $targetDefinition,
        public readonly ?array $metadata,
    ) {}

    /**
     * Create from a Voucher model.
     */
    public static function fromModel(\AIArmada\Vouchers\Models\Voucher $voucher): self
    {
        $type = $voucher->type;

        if (! $type instanceof VoucherType) {
            $type = VoucherType::from($type);
        }

        $status = $voucher->status;

        if (! $status instanceof VoucherStatus) {
            $status = VoucherStatus::from($status);
        }

        return new self(
            id: $voucher->id,
            code: $voucher->code,
            name: $voucher->name,
            description: $voucher->description,
            type: $type,
            value: (float) $voucher->value,
            valueConfig: $voucher->value_config,
            creditDestination: $voucher->credit_destination,
            creditDelayHours: (int) ($voucher->credit_delay_hours ?? 0),
            currency: $voucher->currency,
            minCartValue: $voucher->min_cart_value ? (float) $voucher->min_cart_value : null,
            maxDiscount: $voucher->max_discount ? (float) $voucher->max_discount : null,
            usageLimit: $voucher->usage_limit,
            usageLimitPerUser: $voucher->usage_limit_per_user,
            allowsManualRedemption: (bool) $voucher->allows_manual_redemption,
            ownerId: $voucher->owner_id,
            ownerType: $voucher->owner_type,
            startsAt: $voucher->starts_at,
            expiresAt: $voucher->expires_at,
            status: $status,
            targetDefinition: $voucher->target_definition,
            metadata: $voucher->metadata,
        );
    }

    /**
     * Create from an array.
     *
     * This method provides backward compatibility with existing code
     * that uses the fromArray factory method.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $startsAt = isset($data['starts_at']) && is_string($data['starts_at'])
            ? CarbonImmutable::parse($data['starts_at'])
            : (isset($data['starts_at']) && $data['starts_at'] instanceof DateTimeInterface
                ? $data['starts_at']
                : null);

        $expiresAt = isset($data['expires_at']) && is_string($data['expires_at'])
            ? CarbonImmutable::parse($data['expires_at'])
            : (isset($data['expires_at']) && $data['expires_at'] instanceof DateTimeInterface
                ? $data['expires_at']
                : null);

        /** @var string|int $typeValue */
        $typeValue = $data['type'] ?? VoucherType::Fixed->value;
        /** @var string|int $statusValue */
        $statusValue = $data['status'] ?? VoucherStatus::Active->value;

        /** @var int|string|null $ownerId */
        $ownerId = $data['owner_id'] ?? null;

        /** @var array<string, mixed>|null $targetDefinition */
        $targetDefinition = isset($data['target_definition']) && is_array($data['target_definition'])
            ? $data['target_definition']
            : null;

        /** @var array<string, mixed>|null $metadata */
        $metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? $data['metadata']
            : null;

        /** @var array<string, mixed>|null $valueConfig */
        $valueConfig = isset($data['value_config']) && is_array($data['value_config'])
            ? $data['value_config']
            : null;

        /** @var scalar|null $id */
        $id = $data['id'] ?? '';
        /** @var scalar|null $code */
        $code = $data['code'] ?? '';
        /** @var scalar|null $name */
        $name = $data['name'] ?? '';
        /** @var scalar|null $description */
        $description = $data['description'] ?? null;
        /** @var scalar|null $value */
        $value = $data['value'] ?? 0.0;
        /** @var scalar|null $currency */
        $currency = $data['currency'] ?? 'MYR';
        /** @var scalar|null $minCartValue */
        $minCartValue = $data['min_cart_value'] ?? null;
        /** @var scalar|null $maxDiscount */
        $maxDiscount = $data['max_discount'] ?? null;
        /** @var scalar|null $usageLimit */
        $usageLimit = $data['usage_limit'] ?? null;
        /** @var scalar|null $usageLimitPerUser */
        $usageLimitPerUser = $data['usage_limit_per_user'] ?? null;
        /** @var scalar|null $ownerType */
        $ownerType = $data['owner_type'] ?? null;
        /** @var scalar|null $creditDestination */
        $creditDestination = $data['credit_destination'] ?? null;
        /** @var scalar|null $creditDelayHours */
        $creditDelayHours = $data['credit_delay_hours'] ?? 0;

        $type = $typeValue instanceof VoucherType
            ? $typeValue
            : VoucherType::from((string) $typeValue);

        $status = $statusValue instanceof VoucherStatus
            ? $statusValue
            : VoucherStatus::from((string) $statusValue);

        return new self(
            id: (string) $id,
            code: (string) $code,
            name: (string) $name,
            description: $description !== null ? (string) $description : null,
            type: $type,
            value: (float) $value,
            valueConfig: $valueConfig,
            creditDestination: $creditDestination !== null ? (string) $creditDestination : null,
            creditDelayHours: (int) $creditDelayHours,
            currency: (string) $currency,
            minCartValue: $minCartValue !== null ? (float) $minCartValue : null,
            maxDiscount: $maxDiscount !== null ? (float) $maxDiscount : null,
            usageLimit: $usageLimit !== null ? (int) $usageLimit : null,
            usageLimitPerUser: $usageLimitPerUser !== null ? (int) $usageLimitPerUser : null,
            allowsManualRedemption: isset($data['allows_manual_redemption']) && (bool) $data['allows_manual_redemption'],
            ownerId: $ownerId,
            ownerType: $ownerType !== null ? (string) $ownerType : null,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            status: $status,
            targetDefinition: $targetDefinition,
            metadata: $metadata,
        );
    }

    /**
     * Check if the voucher is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === VoucherStatus::Active;
    }

    /**
     * Check if the voucher is a percentage discount.
     */
    public function isPercentage(): bool
    {
        return $this->type === VoucherType::Percentage;
    }

    /**
     * Check if the voucher is a fixed amount discount.
     */
    public function isFixed(): bool
    {
        return $this->type === VoucherType::Fixed;
    }

    /**
     * Check if the voucher provides free shipping.
     */
    public function isFreeShipping(): bool
    {
        return $this->type === VoucherType::FreeShipping;
    }

    /**
     * Check if the voucher is a compound type.
     */
    public function isCompound(): bool
    {
        return $this->type->isCompound();
    }

    /**
     * Check if the voucher has expired.
     */
    public function hasExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < now();
    }

    /**
     * Check if the voucher has started.
     */
    public function hasStarted(): bool
    {
        if ($this->startsAt === null) {
            return true;
        }

        return $this->startsAt <= now();
    }

    /**
     * Check if the voucher is within its valid date range.
     */
    public function isWithinDateRange(): bool
    {
        return $this->hasStarted() && ! $this->hasExpired();
    }

    /**
     * Check if the voucher has a minimum cart value requirement.
     */
    public function hasMinCartValue(): bool
    {
        return $this->minCartValue !== null && $this->minCartValue > 0;
    }

    /**
     * Check if a cart value meets the minimum requirement.
     */
    public function meetsMinCartValue(float $cartValue): bool
    {
        if (! $this->hasMinCartValue()) {
            return true;
        }

        return $cartValue >= $this->minCartValue;
    }

    /**
     * Get the formatted value for display.
     */
    public function getFormattedValue(): string
    {
        if ($this->isPercentage()) {
            return $this->value.'%';
        }

        return $this->currency.' '.number_format($this->value, 2);
    }
}
