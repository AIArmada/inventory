<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\Chip\Events\PurchaseCancelled;

/**
 * Handles purchase.cancelled webhook events.
 */
class PurchaseCancelledHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchase = $payload->localPurchase;

        if ($localPurchase === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        // Update local status
        $localPurchase->update([
            'status' => PurchaseStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);

        // Emit Laravel event
        event(new PurchaseCancelled(
            purchase: \AIArmada\Chip\Data\PurchaseData::from($payload->rawPayload),
            payload: $payload->rawPayload,
        ));

        return WebhookResult::handled("Purchase {$localPurchase->id} marked as cancelled");
    }
}
