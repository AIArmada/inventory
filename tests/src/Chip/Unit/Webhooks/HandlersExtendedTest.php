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

/**
 * @param  array<string, mixed>  $rawPayload
 */
function createTestEnrichedPayload(string $event, array $rawPayload = []): EnrichedWebhookPayload
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

describe('PurchasePaidHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new PurchasePaidHandler;
        expect($handler)->toBeInstanceOf(PurchasePaidHandler::class);
    });

    it('returns skipped result when no local purchase exists', function (): void {
        $handler = new PurchasePaidHandler;
        $payload = createTestEnrichedPayload('purchase.paid');

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });

    it('has handle method that accepts EnrichedWebhookPayload', function (): void {
        $handler = new PurchasePaidHandler;
        $reflection = new ReflectionMethod($handler, 'handle');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(EnrichedWebhookPayload::class);
    });
});

describe('PurchaseCancelledHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new PurchaseCancelledHandler;
        expect($handler)->toBeInstanceOf(PurchaseCancelledHandler::class);
    });

    it('returns skipped result when no local purchase exists', function (): void {
        $handler = new PurchaseCancelledHandler;
        $payload = createTestEnrichedPayload('purchase.cancelled');

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });
});

describe('PaymentFailedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new PaymentFailedHandler;
        expect($handler)->toBeInstanceOf(PaymentFailedHandler::class);
    });

    it('returns skipped result when no local purchase exists', function (): void {
        $handler = new PaymentFailedHandler;
        $payload = createTestEnrichedPayload('purchase.payment_failure');

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });

    it('handles payload with failure reason', function (): void {
        $handler = new PaymentFailedHandler;
        $payload = createTestEnrichedPayload('purchase.payment_failure', [
            'status' => 'failed',
            'failure_reason' => 'Insufficient funds',
        ]);

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
    });
});

describe('PurchaseRefundedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new PurchaseRefundedHandler;
        expect($handler)->toBeInstanceOf(PurchaseRefundedHandler::class);
    });

    it('returns skipped result when no local purchase exists', function (): void {
        $handler = new PurchaseRefundedHandler;
        $payload = createTestEnrichedPayload('payment.refunded');

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });
});

describe('SendCompletedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new SendCompletedHandler;
        expect($handler)->toBeInstanceOf(SendCompletedHandler::class);
    });

    it('returns skipped result for unknown payout', function (): void {
        $handler = new SendCompletedHandler;
        $payload = createTestEnrichedPayload('payout.success', [
            'id' => 'payout-123',
            'type' => 'payout',
            'status' => 'success',
        ]);

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });

    it('handles payout success payload', function (): void {
        $handler = new SendCompletedHandler;
        $payload = createTestEnrichedPayload('payout.success', [
            'id' => 'send-123',
            'type' => 'send_instruction',
            'status' => 'success',
            'amount' => 10000,
            'currency' => 'MYR',
        ]);

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
    });
});

describe('SendRejectedHandler', function (): void {
    it('can be instantiated', function (): void {
        $handler = new SendRejectedHandler;
        expect($handler)->toBeInstanceOf(SendRejectedHandler::class);
    });

    it('returns skipped result for unknown payout', function (): void {
        $handler = new SendRejectedHandler;
        $payload = createTestEnrichedPayload('payout.failed', [
            'id' => 'payout-123',
            'type' => 'payout',
            'status' => 'failed',
        ]);

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
    });

    it('handles payout failed payload with reason', function (): void {
        $handler = new SendRejectedHandler;
        $payload = createTestEnrichedPayload('payout.failed', [
            'id' => 'send-123',
            'type' => 'send_instruction',
            'status' => 'failed',
            'failure_reason' => 'Invalid bank account',
        ]);

        $result = $handler->handle($payload);

        expect($result)->toBeInstanceOf(WebhookResult::class);
    });
});

describe('Handler edge cases', function (): void {
    it('all handlers handle empty payload gracefully', function (): void {
        $handlers = [
            new PurchasePaidHandler,
            new PurchaseCancelledHandler,
            new PaymentFailedHandler,
            new PurchaseRefundedHandler,
            new SendCompletedHandler,
            new SendRejectedHandler,
        ];

        $events = [
            'purchase.paid',
            'purchase.cancelled',
            'purchase.payment_failure',
            'payment.refunded',
            'payout.success',
            'payout.failed',
        ];

        foreach ($handlers as $index => $handler) {
            $payload = createTestEnrichedPayload($events[$index], []);
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
        }
    });
});
