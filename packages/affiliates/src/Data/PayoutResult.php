<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Result of a payout operation.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class PayoutResult extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalReference = null,
        public readonly ?string $failureReason = null,
        public readonly ?string $failureCode = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function success(string $externalReference, array $metadata = []): self
    {
        return new self(
            success: true,
            externalReference: $externalReference,
            metadata: $metadata
        );
    }

    public static function failure(string $reason, ?string $code = null): self
    {
        return new self(
            success: false,
            failureReason: $reason,
            failureCode: $code
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function pending(string $externalReference, array $metadata = []): self
    {
        return new self(
            success: true,
            externalReference: $externalReference,
            metadata: array_merge($metadata, ['status' => 'pending'])
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isPending(): bool
    {
        return $this->success && ($this->metadata['status'] ?? null) === 'pending';
    }

    public function getStatus(): string
    {
        if (! $this->success) {
            return 'failed';
        }

        return $this->metadata['status'] ?? 'completed';
    }
}
