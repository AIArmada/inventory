<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for all CHIP purchase-related webhook events.
 *
 * Provides common functionality and accessors for purchase data.
 */
abstract class PurchaseEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly PurchaseData $purchase,
        public readonly array $payload,
    ) {}

    /**
     * Get the webhook event type for this event.
     */
    abstract public function eventType(): WebhookEventType;

    /**
     * Create event from a raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     *
     * @phpstan-ignore-next-line new.static - All subclasses are final, this is safe
     */
    final public static function fromPayload(array $payload): static
    {
        $purchase = PurchaseData::fromArray($payload);

        return new static(
            purchase: $purchase,
            payload: $payload,
        );
    }

    /**
     * Get the event type string value.
     */
    final public function getEventTypeValue(): string
    {
        return $this->eventType()->value;
    }

    /**
     * Get the purchase reference.
     */
    final public function getReference(): ?string
    {
        return $this->purchase->reference;
    }

    /**
     * Get the purchase ID.
     */
    final public function getPurchaseId(): string
    {
        return $this->purchase->id;
    }

    /**
     * Get the client ID.
     */
    final public function getClientId(): ?string
    {
        return $this->purchase->client_id;
    }

    /**
     * Get the amount in cents.
     */
    final public function getAmount(): int
    {
        return $this->purchase->getAmountInCents();
    }

    /**
     * Get the currency code.
     */
    final public function getCurrency(): string
    {
        return $this->purchase->getCurrency();
    }

    /**
     * Get the purchase status.
     */
    final public function getStatus(): string
    {
        return $this->purchase->status;
    }

    /**
     * Get the customer email.
     */
    final public function getCustomerEmail(): ?string
    {
        return $this->purchase->client->email ?? null;
    }

    /**
     * Get the customer name.
     */
    final public function getCustomerName(): ?string
    {
        return $this->purchase->client->full_name ?? null;
    }

    /**
     * Get the recurring token if available.
     */
    final public function getRecurringToken(): ?string
    {
        return $this->purchase->recurring_token;
    }

    /**
     * Check if this purchase has a recurring token.
     */
    final public function hasRecurringToken(): bool
    {
        return $this->purchase->recurring_token !== null;
    }

    /**
     * Check if this is a test purchase.
     */
    final public function isTest(): bool
    {
        return $this->purchase->is_test;
    }

    /**
     * Get the payment method used.
     */
    final public function getPaymentMethod(): ?string
    {
        return $this->purchase->transaction_data->payment_method ?? null;
    }

    /**
     * Get purchase metadata.
     *
     * @return array<string, mixed>|null
     */
    final public function getMetadata(): ?array
    {
        return $this->purchase->getMetadata();
    }

    /**
     * Get a specific metadata value.
     */
    final public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        $metadata = $this->getMetadata();

        return $metadata[$key] ?? $default;
    }
}
