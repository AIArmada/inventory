<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\Chip\Models\Purchase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Enriched webhook payload with local context.
 */
final class EnrichedWebhookPayload extends Data
{
    public function __construct(
        public readonly string $event,
        /** @var array<string, mixed> */
        public readonly array $rawPayload,
        public readonly ?Purchase $localPurchase = null,
        public readonly ?Model $owner = null,
        public readonly ?Carbon $receivedAt = null,
        public readonly ?Carbon $eventTimestamp = null,
        public readonly ?string $purchaseId = null,
        public readonly ?string $clientId = null,
    ) {}

    /**
     * Create from raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(string $event, array $payload): self
    {
        $purchaseId = $payload['id'] ?? $payload['data']['id'] ?? null;
        $clientId = $payload['client_id'] ?? $payload['client']['id'] ?? $payload['data']['client_id'] ?? null;

        $localPurchase = null;
        $owner = null;

        if ($purchaseId) {
            $localPurchase = Purchase::where('chip_id', $purchaseId)->first();
            $owner = $localPurchase?->owner;
        }

        $eventTimestamp = isset($payload['created'])
            ? Carbon::parse($payload['created'])
            : (isset($payload['created_on']) ? Carbon::parse($payload['created_on']) : null);

        return new self(
            event: $event,
            rawPayload: $payload,
            localPurchase: $localPurchase,
            owner: $owner,
            receivedAt: now(),
            eventTimestamp: $eventTimestamp,
            purchaseId: $purchaseId,
            clientId: $clientId,
        );
    }

    public function hasLocalPurchase(): bool
    {
        return $this->localPurchase !== null;
    }

    public function hasOwner(): bool
    {
        return $this->owner !== null;
    }

    /**
     * Get a value from the raw payload using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->rawPayload, $key, $default);
    }
}
