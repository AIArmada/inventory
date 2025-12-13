<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Logs and tracks webhook events.
 */
class WebhookLogger
{
    /**
     * Create a webhook log entry.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createLog(
        string $event,
        array $payload,
        Request $request,
        string $idempotencyKey,
    ): Webhook {
        return Webhook::create([
            'event' => $event,
            'payload' => $payload,
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Check if a webhook with this idempotency key already exists.
     */
    public function isDuplicate(string $idempotencyKey): bool
    {
        return Webhook::where('idempotency_key', $idempotencyKey)
            ->where('status', 'processed')
            ->exists();
    }

    /**
     * Generate an idempotency key from the payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function generateIdempotencyKey(array $payload): string
    {
        return hash('sha256', json_encode([
            'event' => $payload['event_type'] ?? $payload['event'] ?? null,
            'object_id' => $payload['id'] ?? $payload['data']['id'] ?? null,
            'created' => $payload['created'] ?? $payload['created_on'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Log an invalid signature attempt.
     */
    public function logInvalidSignature(Request $request): void
    {
        Log::channel(config('chip.logging.channel', 'stack'))
            ->warning('Invalid webhook signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
    }
}
