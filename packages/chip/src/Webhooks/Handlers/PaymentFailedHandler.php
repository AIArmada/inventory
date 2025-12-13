<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\Chip\Events\PurchasePaymentFailure;

/**
 * Handles payment.failed and purchase.payment_failure webhook events.
 */
class PaymentFailedHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchase = $payload->localPurchase;

        if ($localPurchase === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        // Get failure reason from payload
        $failureReason = $payload->get('error.message')
            ?? $payload->get('failure_reason')
            ?? $payload->get('error_message')
            ?? 'Unknown payment failure';

        // Update local status
        $localPurchase->update([
            'status' => PurchaseStatus::ERROR,
            'failure_reason' => $failureReason,
        ]);

        // Emit Laravel event
        event(new PurchasePaymentFailure(
            purchase: \AIArmada\Chip\Data\PurchaseData::from($payload->rawPayload),
            payload: $payload->rawPayload,
        ));

        return WebhookResult::handled("Purchase {$localPurchase->id} marked as failed");
    }
}
