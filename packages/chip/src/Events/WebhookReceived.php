<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Data\BillingTemplateClientData;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a CHIP webhook is received.
 *
 * This is the generic webhook event dispatched for all incoming webhooks.
 * Specific typed events (PurchasePaid, PayoutSuccess, etc.) are also dispatched.
 */
class WebhookReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $eventType,
        public readonly array $payload,
        public readonly ?PurchaseData $purchase = null,
        public readonly ?PayoutData $payout = null,
        public readonly ?BillingTemplateClientData $billingTemplateClient = null,
    ) {}

    /**
     * Create event from a raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $eventType = $payload['event_type'] ?? 'unknown';
        $purchase = null;
        $payout = null;
        $billingTemplateClient = null;

        $type = $payload['type'] ?? '';

        if ($type === 'purchase' || str_starts_with($eventType, 'purchase.')) {
            $purchase = PurchaseData::fromArray($payload);
        } elseif ($type === 'payout' || str_starts_with($eventType, 'payout.')) {
            $payout = PayoutData::fromArray($payload);
        } elseif ($type === 'billing_template_client' || str_starts_with($eventType, 'billing_template_client.')) {
            $billingTemplateClient = BillingTemplateClientData::fromArray($payload);
        }

        return new self(
            eventType: $eventType,
            payload: $payload,
            purchase: $purchase,
            payout: $payout,
            billingTemplateClient: $billingTemplateClient,
        );
    }

    /**
     * Get the webhook event type enum if valid.
     */
    public function getEventTypeEnum(): ?WebhookEventType
    {
        return WebhookEventType::fromString($this->eventType);
    }

    // Purchase lifecycle events

    public function isCreated(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseCreated->value;
    }

    public function isPaid(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePaid->value;
    }

    public function isPaymentFailure(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePaymentFailure->value;
    }

    public function isCancelled(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseCancelled->value;
    }

    // Pending events

    public function isPendingExecute(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingExecute->value;
    }

    public function isPendingCharge(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingCharge->value;
    }

    public function isPendingCapture(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingCapture->value;
    }

    public function isPendingRelease(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingRelease->value;
    }

    public function isPendingRefund(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingRefund->value;
    }

    public function isPendingRecurringTokenDelete(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePendingRecurringTokenDelete->value;
    }

    // Authorization/capture events

    public function isHold(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseHold->value;
    }

    public function isCaptured(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseCaptured->value;
    }

    public function isReleased(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseReleased->value;
    }

    public function isPreauthorized(): bool
    {
        return $this->eventType === WebhookEventType::PurchasePreauthorized->value;
    }

    // Recurring token events

    public function isRecurringTokenDeleted(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseRecurringTokenDeleted->value;
    }

    // Subscription events

    public function isSubscriptionChargeFailure(): bool
    {
        return $this->eventType === WebhookEventType::PurchaseSubscriptionChargeFailure->value;
    }

    // Refund events

    public function isRefunded(): bool
    {
        return $this->eventType === WebhookEventType::PaymentRefunded->value;
    }

    // Billing events

    public function isBillingCancelled(): bool
    {
        return $this->eventType === WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled->value;
    }

    // Payout events

    public function isPayoutPending(): bool
    {
        return $this->eventType === WebhookEventType::PayoutPending->value;
    }

    public function isPayoutFailed(): bool
    {
        return $this->eventType === WebhookEventType::PayoutFailed->value;
    }

    public function isPayoutSuccess(): bool
    {
        return $this->eventType === WebhookEventType::PayoutSuccess->value;
    }

    // Category checks

    public function isPurchaseEvent(): bool
    {
        return str_starts_with($this->eventType, 'purchase.');
    }

    public function isPayoutEvent(): bool
    {
        return str_starts_with($this->eventType, 'payout.');
    }

    public function isBillingEvent(): bool
    {
        return str_starts_with($this->eventType, 'billing_template_client.');
    }

    public function isPaymentEvent(): bool
    {
        return str_starts_with($this->eventType, 'payment.');
    }

    public function isPendingEvent(): bool
    {
        return str_contains($this->eventType, 'pending');
    }

    public function isSuccessEvent(): bool
    {
        return in_array($this->eventType, [
            WebhookEventType::PurchasePaid->value,
            WebhookEventType::PurchaseCaptured->value,
            WebhookEventType::PurchaseReleased->value,
            WebhookEventType::PurchasePreauthorized->value,
            WebhookEventType::PayoutSuccess->value,
        ]);
    }

    public function isFailureEvent(): bool
    {
        return in_array($this->eventType, [
            WebhookEventType::PurchasePaymentFailure->value,
            WebhookEventType::PurchaseSubscriptionChargeFailure->value,
            WebhookEventType::PayoutFailed->value,
        ]);
    }

    // Data accessors

    public function getReference(): ?string
    {
        return $this->payload['reference'] ?? null;
    }

    public function getPurchaseId(): ?string
    {
        return $this->payload['id'] ?? null;
    }

    public function getClientId(): ?string
    {
        return $this->payload['client_id'] ?? null;
    }

    /**
     * Get the amount in cents.
     */
    public function getAmount(): int
    {
        return $this->payload['purchase']['total'] ?? $this->payload['amount'] ?? 0;
    }

    public function getCurrency(): string
    {
        return $this->payload['purchase']['currency'] ?? $this->payload['currency'] ?? 'MYR';
    }

    /**
     * Check if this is a test webhook.
     */
    public function isTest(): bool
    {
        return $this->payload['is_test'] ?? true;
    }
}
