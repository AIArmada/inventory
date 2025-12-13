<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Data\EnrichedWebhookPayload;

/**
 * Enriches webhook payloads with local context.
 */
class WebhookEnricher
{
    /**
     * Enrich a webhook payload with local context.
     *
     * @param  array<string, mixed>  $payload
     */
    public function enrich(string $event, array $payload): EnrichedWebhookPayload
    {
        return EnrichedWebhookPayload::fromPayload($event, $payload);
    }
}
