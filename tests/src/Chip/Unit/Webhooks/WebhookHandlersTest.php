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

describe('WebhookHandler interface implementations', function (): void {
    describe('PurchasePaidHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = new PurchasePaidHandler();
            expect($handler)->toBeInstanceOf(PurchasePaidHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = new PurchasePaidHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('has handle method', function (): void {
            expect(method_exists(PurchasePaidHandler::class, 'handle'))->toBeTrue();
        });
    });

    describe('PurchaseCancelledHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = new PurchaseCancelledHandler();
            expect($handler)->toBeInstanceOf(PurchaseCancelledHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = new PurchaseCancelledHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

    describe('PaymentFailedHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = new PaymentFailedHandler();
            expect($handler)->toBeInstanceOf(PaymentFailedHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = new PaymentFailedHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

    describe('PurchaseRefundedHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = new PurchaseRefundedHandler();
            expect($handler)->toBeInstanceOf(PurchaseRefundedHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = new PurchaseRefundedHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

    describe('SendCompletedHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = new SendCompletedHandler();
            expect($handler)->toBeInstanceOf(SendCompletedHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = new SendCompletedHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

    describe('SendRejectedHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = new SendRejectedHandler();
            expect($handler)->toBeInstanceOf(SendRejectedHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = new SendRejectedHandler();
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });
});

describe('WebhookResult DTO', function (): void {
    it('can create handled result', function (): void {
        $result = WebhookResult::handled('Test message');

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isHandled())->toBeTrue();
        expect($result->isSkipped())->toBeFalse();
        expect($result->message)->toBe('Test message');
    });

    it('can create skipped result', function (): void {
        $result = WebhookResult::skipped('Skipped because...');

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isSkipped())->toBeTrue();
        expect($result->isHandled())->toBeFalse();
        expect($result->message)->toBe('Skipped because...');
    });

    it('can create failed result', function (): void {
        $result = WebhookResult::failed('Error occurred');

        expect($result)->toBeInstanceOf(WebhookResult::class);
        expect($result->isFailed())->toBeTrue();
        expect($result->isHandled())->toBeFalse();
        expect($result->isSkipped())->toBeFalse();
    });
});

describe('EnrichedWebhookPayload DTO', function (): void {
    it('can be instantiated', function (): void {
        $payload = new EnrichedWebhookPayload(
            event: 'purchase.paid',
            rawPayload: ['id' => 'purchase-456', 'status' => 'paid'],
            localPurchase: null,
            owner: null,
            receivedAt: now(),
            purchaseId: 'purchase-456',
        );

        expect($payload)->toBeInstanceOf(EnrichedWebhookPayload::class);
        expect($payload->event)->toBe('purchase.paid');
        expect($payload->purchaseId)->toBe('purchase-456');
        expect($payload->localPurchase)->toBeNull();
    });

    it('has rawPayload array', function (): void {
        $rawData = ['id' => 'purchase-456', 'status' => 'paid', 'amount' => 10000];
        $payload = new EnrichedWebhookPayload(
            event: 'purchase.paid',
            rawPayload: $rawData,
            localPurchase: null,
            purchaseId: 'purchase-456',
        );

        expect($payload->rawPayload)->toBe($rawData);
        expect($payload->rawPayload['id'])->toBe('purchase-456');
        expect($payload->rawPayload['status'])->toBe('paid');
        expect($payload->rawPayload['amount'])->toBe(10000);
    });

    it('can get value from payload using dot notation', function (): void {
        $rawData = [
            'id' => 'purchase-456',
            'payment' => ['amount' => 10000, 'currency' => 'MYR'],
        ];
        $payload = new EnrichedWebhookPayload(
            event: 'purchase.paid',
            rawPayload: $rawData,
            localPurchase: null,
        );

        expect($payload->get('id'))->toBe('purchase-456');
        expect($payload->get('payment.amount'))->toBe(10000);
        expect($payload->get('payment.currency'))->toBe('MYR');
        expect($payload->get('nonexistent', 'default'))->toBe('default');
    });

    it('hasLocalPurchase returns false when null', function (): void {
        $payload = new EnrichedWebhookPayload(
            event: 'purchase.paid',
            rawPayload: [],
            localPurchase: null,
        );

        expect($payload->hasLocalPurchase())->toBeFalse();
    });

    it('hasOwner returns false when null', function (): void {
        $payload = new EnrichedWebhookPayload(
            event: 'purchase.paid',
            rawPayload: [],
            owner: null,
        );

        expect($payload->hasOwner())->toBeFalse();
    });
});
