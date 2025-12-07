<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a payment is refunded.
 *
 * Note: The payment.refunded webhook actually returns a Purchase object
 * with updated status, not a separate Payment object.
 */
final class PaymentRefunded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly ?PurchaseData $purchase,
        public readonly array $payload,
    ) {}

    /**
     * Create event from a raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $purchase = PurchaseData::fromArray($payload);

        return new self(
            purchase: $purchase,
            payload: $payload,
        );
    }

    public function eventType(): WebhookEventType
    {
        return WebhookEventType::PaymentRefunded;
    }

    /**
     * Get the refund amount in cents.
     */
    public function getAmount(): int
    {
        return $this->purchase?->getAmountInCents() ?? 0;
    }

    /**
     * Get the currency code.
     */
    public function getCurrency(): string
    {
        return $this->purchase?->getCurrency() ?? 'MYR';
    }

    /**
     * Get the purchase ID.
     */
    public function getPurchaseId(): ?string
    {
        return $this->purchase?->id;
    }

    /**
     * Get the reference.
     */
    public function getReference(): ?string
    {
        return $this->purchase?->reference;
    }

    /**
     * Check if this is a test payment.
     */
    public function isTest(): bool
    {
        return $this->purchase->is_test ?? true;
    }
}
