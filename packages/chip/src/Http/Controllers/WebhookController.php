<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Controllers;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookEventDispatcher $dispatcher,
    ) {}

    /**
     * Handle incoming CHIP webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = $payload['event_type'] ?? 'unknown';

        // Generate idempotency key from payload for deduplication
        $idempotencyKey = $this->generateIdempotencyKey($payload);

        // Check for duplicate webhook (deduplication)
        if ($this->isDuplicateWebhook($idempotencyKey)) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->info('CHIP webhook skipped - duplicate', [
                    'idempotency_key' => $idempotencyKey,
                    'event_type' => $eventType,
                ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Duplicate webhook ignored',
            ]);
        }

        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owner = ChipWebhookOwnerResolver::resolveFromPayload($payload);

            if ($owner === null) {
                Log::channel(config('chip.logging.channel', 'stack'))
                    ->error('CHIP webhook received but no owner could be resolved for brand_id', [
                        'event_type' => $eventType,
                        'brand_id' => $payload['brand_id'] ?? null,
                    ]);

                return response()->json([
                    'error' => 'Owner resolution failed',
                ], 500);
            }

            return OwnerContext::withOwner($owner, fn (): JsonResponse => $this->handleScoped($eventType, $payload, $idempotencyKey));
        }

        return $this->handleScoped($eventType, $payload, $idempotencyKey);
    }

    /**
     * Generate a unique idempotency key from the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function generateIdempotencyKey(array $payload): string
    {
        // Combine unique identifiers from the payload
        $components = [
            $payload['event_type'] ?? 'unknown',
            $payload['id'] ?? '',
            $payload['status'] ?? '',
            $payload['updated_on'] ?? $payload['created_on'] ?? '',
        ];

        return hash('sha256', implode(':', $components));
    }

    /**
     * Check if this webhook has already been processed.
     */
    private function isDuplicateWebhook(string $idempotencyKey): bool
    {
        if (! config('chip.webhooks.deduplication', true)) {
            return false;
        }

        return Webhook::where('idempotency_key', $idempotencyKey)
            ->where('processed', true)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleScoped(string $eventType, array $payload, string $idempotencyKey): JsonResponse
    {
        $startTime = microtime(true);

        // Store webhook record with idempotency key
        $webhook = $this->storeWebhookRecord($eventType, $payload, $idempotencyKey);

        try {
            // Dispatch the generic WebhookReceived event
            WebhookReceived::dispatch(
                $eventType,
                $payload,
                $this->dispatcher->extractPurchase($payload),
                $this->dispatcher->extractPayout($payload),
                $this->dispatcher->extractBillingTemplateClient($payload),
            );

            // Dispatch the specific typed event using the centralized dispatcher
            $this->dispatcher->dispatch($eventType, $payload);

            // Mark as processed
            $processingTime = (microtime(true) - $startTime) * 1000;
            $webhook?->markProcessed($processingTime);

            return response()->json([
                'status' => 'ok',
                'event_type' => $eventType,
            ]);
        } catch (Throwable $e) {
            $webhook?->markFailed($e);

            throw $e;
        }
    }

    /**
     * Store webhook record for tracking and deduplication.
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeWebhookRecord(string $eventType, array $payload, string $idempotencyKey): ?Webhook
    {
        if (! config('chip.webhooks.store_webhooks', true)) {
            return null;
        }

        return Webhook::create([
            'event' => $eventType,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'status' => 'pending',
            'verified' => true, // Already verified by middleware
            'processed' => false,
            // Required fields from original schema (webhook configuration fields)
            'title' => 'Incoming: ' . $eventType,
            'events' => [$eventType],
            'callback' => request()->url(),
            'created_on' => $payload['created_on'] ?? time(),
            'updated_on' => $payload['updated_on'] ?? time(),
        ]);
    }
}
