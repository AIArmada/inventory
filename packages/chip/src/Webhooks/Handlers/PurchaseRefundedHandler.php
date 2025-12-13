<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\Chip\Events\PaymentRefunded;

/**
 * Handles purchase.refunded and payment.refunded webhook events.
 */
class PurchaseRefundedHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchase = $payload->localPurchase;

        if ($localPurchase === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        // Update local status
        $refundAmount = $payload->get('refund_amount') ?? $payload->get('refunded_amount');

        $localPurchase->update([
            'status' => PurchaseStatus::REFUNDED,
            'refund_amount_minor' => $refundAmount,
            'refunded_at' => now(),
        ]);

        // Emit Laravel event
        event(new PaymentRefunded(
            purchase: \AIArmada\Chip\Data\PurchaseData::from($payload->rawPayload),
            payload: $payload->rawPayload,
        ));

        return WebhookResult::handled("Purchase {$localPurchase->id} marked as refunded");
    }
}
