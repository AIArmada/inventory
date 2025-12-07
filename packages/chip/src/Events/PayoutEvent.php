<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Enums\WebhookEventType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for payout events.
 */
abstract class PayoutEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly PayoutData $payout,
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
        $payout = PayoutData::fromArray($payload);

        return new static(
            payout: $payout,
            payload: $payload,
        );
    }

    /**
     * Get the payout ID.
     */
    final public function getPayoutId(): string
    {
        return $this->payout->id;
    }

    /**
     * Get the payout amount in cents.
     */
    final public function getAmount(): int
    {
        return $this->payout->getAmountInCents();
    }

    /**
     * Get the currency code.
     */
    final public function getCurrency(): string
    {
        return $this->payout->currency;
    }

    /**
     * Get the payout status.
     */
    final public function getStatus(): string
    {
        return $this->payout->status;
    }

    /**
     * Get the payout reference.
     */
    final public function getReference(): ?string
    {
        return $this->payout->reference;
    }

    /**
     * Get the recipient name.
     */
    final public function getRecipientName(): ?string
    {
        return $this->payout->recipient_name;
    }

    /**
     * Get the recipient bank account.
     */
    final public function getRecipientBankAccount(): ?string
    {
        return $this->payout->recipient_bank_account;
    }

    /**
     * Check if this is a test payout.
     */
    final public function isTest(): bool
    {
        return $this->payout->is_test;
    }
}
