<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Data\BillingTemplateClientData;
use AIArmada\Chip\Enums\WebhookEventType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a subscriber cancels their subscription via email link.
 */
final class BillingCancelled
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly BillingTemplateClientData $billingTemplateClient,
        public readonly array $payload,
    ) {}

    /**
     * Create event from a raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $billingTemplateClient = BillingTemplateClientData::fromArray($payload);

        return new self(
            billingTemplateClient: $billingTemplateClient,
            payload: $payload,
        );
    }

    public function eventType(): WebhookEventType
    {
        return WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled;
    }

    /**
     * Get the billing template client ID.
     */
    public function getBillingTemplateClientId(): string
    {
        return $this->billingTemplateClient->id;
    }

    /**
     * Get the billing template ID.
     */
    public function getBillingTemplateId(): string
    {
        return $this->billingTemplateClient->billing_template_id;
    }

    /**
     * Get the client ID.
     */
    public function getClientId(): string
    {
        return $this->billingTemplateClient->client_id;
    }

    /**
     * Check if this is a test event.
     */
    public function isTest(): bool
    {
        return $this->billingTemplateClient->is_test;
    }
}
