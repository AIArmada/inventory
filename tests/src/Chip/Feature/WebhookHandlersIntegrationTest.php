<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Webhooks\Handlers\PaymentFailedHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseCancelledHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseRefundedHandler;
use AIArmada\Chip\Webhooks\Handlers\SendCompletedHandler;
use AIArmada\Chip\Webhooks\Handlers\SendRejectedHandler;
use AIArmada\Chip\Webhooks\Handlers\WebhookHandler;

describe('Webhook Handlers Integration', function (): void {
    /**
     * @param array<string, mixed> $rawPayload
     */
    function createPayload(string $event, array $rawPayload = []): EnrichedWebhookPayload
    {
        return new EnrichedWebhookPayload(
            event: $event,
            rawPayload: array_merge([
                'id' => 'purchase-' . uniqid(),
                'type' => 'purchase',
                'status' => 'paid',
                'is_test' => true,
                'client_id' => 'client-123',
                'created_on' => time(),
                'updated_on' => time(),
            ], $rawPayload),
            localPurchase: null,
            owner: null,
            receivedAt: now(),
            purchaseId: $rawPayload['id'] ?? 'purchase-123',
            clientId: $rawPayload['client_id'] ?? 'client-123',
        );
    }

    describe('Handler class structure', function (): void {
        it('PurchasePaidHandler extends WebhookHandler', function (): void {
            $handler = new PurchasePaidHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('PurchaseCancelledHandler extends WebhookHandler', function (): void {
            $handler = new PurchaseCancelledHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('PaymentFailedHandler extends WebhookHandler', function (): void {
            $handler = new PaymentFailedHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('PurchaseRefundedHandler extends WebhookHandler', function (): void {
            $handler = new PurchaseRefundedHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('SendCompletedHandler extends WebhookHandler', function (): void {
            $handler = new SendCompletedHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('SendRejectedHandler extends WebhookHandler', function (): void {
            $handler = new SendRejectedHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

    describe('Handler skip behavior without local purchase', function (): void {
        it('PurchasePaidHandler skips when no local purchase exists', function (): void {
            $payload = createPayload('purchase.paid', ['status' => 'paid']);

            $handler = new PurchasePaidHandler();
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });

        it('PurchaseCancelledHandler skips when no local purchase exists', function (): void {
            $payload = createPayload('purchase.cancelled', ['status' => 'cancelled']);

            $handler = new PurchaseCancelledHandler();
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });

        it('PaymentFailedHandler skips when no local purchase exists', function (): void {
            $payload = createPayload('purchase.payment_failure', ['status' => 'failed']);

            $handler = new PaymentFailedHandler();
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });

        it('PurchaseRefundedHandler skips when no local purchase exists', function (): void {
            $payload = createPayload('payment.refunded', ['status' => 'refunded']);

            $handler = new PurchaseRefundedHandler();
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });

        it('SendCompletedHandler skips for unknown payout', function (): void {
            $payload = createPayload('payout.success', [
                'id' => 'payout-123',
                'type' => 'payout',
                'status' => 'success',
            ]);

            $handler = new SendCompletedHandler();
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });

        it('SendRejectedHandler skips for unknown payout', function (): void {
            $payload = createPayload('payout.failed', [
                'id' => 'payout-123',
                'type' => 'payout',
                'status' => 'failed',
            ]);

            $handler = new SendRejectedHandler();
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });
    });

    describe('Handler method signatures', function (): void {
        it('PurchasePaidHandler has handle method with EnrichedWebhookPayload', function (): void {
            $handler = new PurchasePaidHandler();
            $reflection = new ReflectionMethod($handler, 'handle');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(EnrichedWebhookPayload::class);
        });

        it('PurchasePaidHandler returns WebhookResult', function (): void {
            $handler = new PurchasePaidHandler();
            $reflection = new ReflectionMethod($handler, 'handle');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe(WebhookResult::class);
        });
    });

    describe('WebhookResult states', function (): void {
        it('can create handled result', function (): void {
            $result = WebhookResult::handled('Test message');

            expect($result->isHandled())->toBeTrue();
            expect($result->isSkipped())->toBeFalse();
            expect($result->isFailed())->toBeFalse();
        });

        it('can create skipped result', function (): void {
            $result = WebhookResult::skipped('Test skip reason');

            expect($result->isSkipped())->toBeTrue();
            expect($result->isHandled())->toBeFalse();
            expect($result->isFailed())->toBeFalse();
        });

        it('can create failed result', function (): void {
            $result = WebhookResult::failed('Test error');

            expect($result->isFailed())->toBeTrue();
            expect($result->isHandled())->toBeFalse();
            expect($result->isSkipped())->toBeFalse();
        });
    });
});
