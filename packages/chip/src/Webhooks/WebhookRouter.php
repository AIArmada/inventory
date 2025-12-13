<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Webhooks\Handlers\PaymentFailedHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseCancelledHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseRefundedHandler;
use AIArmada\Chip\Webhooks\Handlers\SendCompletedHandler;
use AIArmada\Chip\Webhooks\Handlers\SendRejectedHandler;
use AIArmada\Chip\Webhooks\Handlers\WebhookHandler;

/**
 * Routes webhook events to appropriate handlers.
 */
class WebhookRouter
{
    /**
     * @var array<string, class-string<WebhookHandler>>
     */
    protected array $handlers = [
        'purchase.paid' => PurchasePaidHandler::class,
        'purchase.cancelled' => PurchaseCancelledHandler::class,
        'purchase.refunded' => PurchaseRefundedHandler::class,
        'payment.refunded' => PurchaseRefundedHandler::class,
        'purchase.payment_failure' => PaymentFailedHandler::class,
        'payment.failed' => PaymentFailedHandler::class,
        'send_instruction.completed' => SendCompletedHandler::class,
        'send_instruction.rejected' => SendRejectedHandler::class,
        'payout.success' => SendCompletedHandler::class,
        'payout.failed' => SendRejectedHandler::class,
    ];

    /**
     * Route the webhook to the appropriate handler.
     */
    public function route(string $event, EnrichedWebhookPayload $payload): WebhookResult
    {
        $handlerClass = $this->handlers[$event] ?? null;

        if ($handlerClass === null) {
            return WebhookResult::skipped("No handler registered for event: {$event}");
        }

        /** @var WebhookHandler $handler */
        $handler = app($handlerClass);

        return $handler->handle($payload);
    }

    /**
     * Register a custom handler for an event.
     *
     * @param  class-string<WebhookHandler>  $handlerClass
     */
    public function registerHandler(string $event, string $handlerClass): self
    {
        $this->handlers[$event] = $handlerClass;

        return $this;
    }

    /**
     * Check if a handler exists for the event.
     */
    public function hasHandler(string $event): bool
    {
        return isset($this->handlers[$event]);
    }

    /**
     * Get all registered handlers.
     *
     * @return array<string, class-string<WebhookHandler>>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
