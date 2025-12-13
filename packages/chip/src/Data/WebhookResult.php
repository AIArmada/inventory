<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Spatie\LaravelData\Data;

/**
 * Result of webhook processing.
 */
final class WebhookResult extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly bool $handled,
        public readonly ?string $message = null,
        /** @var array<string, mixed> */
        public readonly array $meta = [],
    ) {}

    /**
     * Create a successful handled result.
     */
    public static function handled(?string $message = null): self
    {
        return new self(
            success: true,
            handled: true,
            message: $message ?? 'Webhook handled successfully',
        );
    }

    /**
     * Create a skipped result (valid but no handler).
     */
    public static function skipped(string $reason): self
    {
        return new self(
            success: true,
            handled: false,
            message: $reason,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function failed(string $message, array $meta = []): self
    {
        return new self(
            success: false,
            handled: false,
            message: $message,
            meta: $meta,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function isSkipped(): bool
    {
        return $this->success && ! $this->handled;
    }

    public function isFailed(): bool
    {
        return ! $this->success;
    }
}
