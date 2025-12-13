<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Data\BillingTemplateClientData;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PayoutFailed;
use AIArmada\Chip\Events\PayoutPending;
use AIArmada\Chip\Events\PayoutSuccess;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchaseCaptured;
use AIArmada\Chip\Events\PurchaseCreated;
use AIArmada\Chip\Events\PurchaseHold;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePendingCapture;
use AIArmada\Chip\Events\PurchasePendingCharge;
use AIArmada\Chip\Events\PurchasePendingExecute;
use AIArmada\Chip\Events\PurchasePendingRecurringTokenDelete;
use AIArmada\Chip\Events\PurchasePendingRefund;
use AIArmada\Chip\Events\PurchasePendingRelease;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseRecurringTokenDeleted;
use AIArmada\Chip\Events\PurchaseReleased;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;

/**
 * Process CHIP webhook events using spatie/laravel-webhook-client.
 *
 * This job handles incoming CHIP webhooks and dispatches the appropriate events.
 *
 * @property WebhookCall $webhookCall
 */
class ProcessChipWebhook extends CommerceWebhookProcessor
{
    /**
     * Process the webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $type = WebhookEventType::fromString($eventType);

        if ($type === null) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('Unknown CHIP webhook event type', [
                    'event_type' => $eventType,
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

            return;
        }

        match ($type) {
            // Purchase lifecycle events
            WebhookEventType::PurchaseCreated => PurchaseCreated::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePaid => PurchasePaid::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePaymentFailure => PurchasePaymentFailure::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseCancelled => PurchaseCancelled::dispatch($this->extractPurchase($payload), $payload),

            // Pending events
            WebhookEventType::PurchasePendingExecute => PurchasePendingExecute::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingCharge => PurchasePendingCharge::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingCapture => PurchasePendingCapture::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRelease => PurchasePendingRelease::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRefund => PurchasePendingRefund::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRecurringTokenDelete => PurchasePendingRecurringTokenDelete::dispatch($this->extractPurchase($payload), $payload),

            // Authorization/capture events
            WebhookEventType::PurchaseHold => PurchaseHold::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseCaptured => PurchaseCaptured::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseReleased => PurchaseReleased::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePreauthorized => PurchasePreauthorized::dispatch($this->extractPurchase($payload), $payload),

            // Recurring token events
            WebhookEventType::PurchaseRecurringTokenDeleted => PurchaseRecurringTokenDeleted::dispatch($this->extractPurchase($payload), $payload),

            // Subscription events
            WebhookEventType::PurchaseSubscriptionChargeFailure => PurchaseSubscriptionChargeFailure::dispatch($this->extractPurchase($payload), $payload),

            // Refund events
            WebhookEventType::PaymentRefunded => PaymentRefunded::dispatch($this->extractPurchase($payload), $payload),

            // Billing events
            WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled => BillingCancelled::dispatch($this->extractBillingTemplateClient($payload), $payload),

            // Payout events
            WebhookEventType::PayoutPending => PayoutPending::dispatch($this->extractPayout($payload), $payload),
            WebhookEventType::PayoutFailed => PayoutFailed::dispatch($this->extractPayout($payload), $payload),
            WebhookEventType::PayoutSuccess => PayoutSuccess::dispatch($this->extractPayout($payload), $payload),
        };
    }

    /**
     * Extract Purchase data object from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractPurchase(array $payload): ?PurchaseData
    {
        $type = $payload['type'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        if ($type === 'purchase' || str_starts_with($eventType, 'purchase.') || str_starts_with($eventType, 'payment.')) {
            return PurchaseData::from($payload);
        }

        return null;
    }

    /**
     * Extract Payout data object from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractPayout(array $payload): ?PayoutData
    {
        $type = $payload['type'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        if ($type === 'payout' || str_starts_with($eventType, 'payout.')) {
            return PayoutData::from($payload);
        }

        return null;
    }

    /**
     * Extract BillingTemplateClientData from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractBillingTemplateClient(array $payload): ?BillingTemplateClientData
    {
        $type = $payload['type'] ?? '';
        $eventType = $payload['event_type'] ?? '';

        if ($type === 'billing_template_client' || str_starts_with($eventType, 'billing_template_client.')) {
            return BillingTemplateClientData::from($payload);
        }

        return null;
    }
}
