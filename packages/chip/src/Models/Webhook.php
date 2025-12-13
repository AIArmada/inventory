<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

/**
 * @property string|null $url
 * @property string|null $event
 * @property string $status
 * @property array<string>|null $events
 * @property array<string, mixed>|null $payload
 * @property array<string, string>|null $headers
 * @property bool $all_events
 * @property bool $verified
 * @property bool $processed
 * @property string|null $idempotency_key
 * @property int $retry_count
 * @property Carbon|null $last_retry_at
 * @property string|null $last_error
 * @property float|null $processing_time_ms
 * @property string|null $ip_address
 * @property int|null $created_on
 * @property int|null $updated_on
 * @property Carbon|null $processed_at
 */
class Webhook extends ChipModel
{
    public $timestamps = true;

    /** @return Attribute<Carbon|null, never> */
    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    /** @return Attribute<Carbon|null, never> */
    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    /**
     * Mark the webhook as processed.
     */
    public function markProcessed(float $processingTimeMs = 0): self
    {
        $this->update([
            'status' => 'processed',
            'processed' => true,
            'processed_at' => now(),
            'processing_time_ms' => $processingTimeMs,
        ]);

        return $this;
    }

    /**
     * Mark the webhook as failed.
     */
    public function markFailed(\Throwable $exception): self
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $exception->getMessage(),
        ]);

        return $this;
    }

    /**
     * Mark the webhook for retry.
     */
    public function markForRetry(string $reason): self
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $reason,
        ]);

        return $this;
    }

    protected static function tableSuffix(): string
    {
        return 'webhooks';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'payload' => 'array',
            'headers' => 'array',
            'all_events' => 'boolean',
            'verified' => 'boolean',
            'processed' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'processed_at' => 'datetime',
            'last_retry_at' => 'datetime',
            'retry_count' => 'integer',
            'processing_time_ms' => 'float',
        ];
    }
}
