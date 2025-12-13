<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;

/**
 * Interface for webhook event handlers.
 */
interface WebhookHandler
{
    /**
     * Handle the webhook event.
     */
    public function handle(EnrichedWebhookPayload $payload): WebhookResult;
}
