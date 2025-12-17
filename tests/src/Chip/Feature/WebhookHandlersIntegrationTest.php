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
     * @param  array<string, mixed>  $rawPayload
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
            $handler = new PurchasePaidHandler;
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('PurchaseCancelledHandler extends WebhookHandler', function (): void {
            $handler = new PurchaseCancelledHandler;
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('PaymentFailedHandler extends WebhookHandler', function (): void {
            $handler = new PaymentFailedHandler;
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('PurchaseRefundedHandler extends WebhookHandler', function (): void {
            $handler = new PurchaseRefundedHandler;
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('SendCompletedHandler extends WebhookHandler', function (): void {
            $handler = new SendCompletedHandler;
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('SendRejectedHandler extends WebhookHandler', function (): void {
            $handler = new SendRejectedHandler;
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

    describe('Handler skip behavior without local purchase', function (): void {
        it('PurchasePaidHandler skips when no local purchase exists', function (): void {
            $payload = createPayload('purchase.paid', ['status' => 'paid']);

            $handler = new PurchasePaidHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });

        it('PurchaseCancelledHandler skips when no local purchase exists', function (): void {
            $payload = createPayload('purchase.cancelled', ['status' => 'cancelled']);

            $handler = new PurchaseCancelledHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });

        it('PaymentFailedHandler skips when no local purchase exists', function (): void {
            $payload = createPayload('purchase.payment_failure', ['status' => 'failed']);

            $handler = new PaymentFailedHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });

        it('PurchaseRefundedHandler skips when no local purchase exists', function (): void {
            $payload = createPayload('payment.refunded', ['status' => 'refunded']);

            $handler = new PurchaseRefundedHandler;
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

            $handler = new SendCompletedHandler;
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

            $handler = new SendRejectedHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isSkipped())->toBeTrue();
        });
    });

    describe('Handler method signatures', function (): void {
        it('PurchasePaidHandler has handle method with EnrichedWebhookPayload', function (): void {
            $handler = new PurchasePaidHandler;
            $reflection = new ReflectionMethod($handler, 'handle');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(EnrichedWebhookPayload::class);
        });

        it('PurchasePaidHandler returns WebhookResult', function (): void {
            $handler = new PurchasePaidHandler;
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

    describe('Handler behavior with local purchase', function (): void {
        /**
         * Create a minimal test purchase with required fields
         *
         * @param  array<string, mixed>  $overrides
         */
        function createTestPurchase(array $overrides = []): AIArmada\Chip\Models\Purchase
        {
            return AIArmada\Chip\Models\Purchase::create(array_merge([
                'id' => 'purchase-' . uniqid(),
                'type' => 'purchase',
                'status' => 'created',
                'brand_id' => 'brand-' . uniqid(),
                'created_on' => time(),
                'updated_on' => time(),
                'client' => ['email' => 'test@example.com'],
                'purchase' => ['total' => 10000, 'currency' => 'MYR'],
                'payment' => null,
                'issuer_details' => [],
                'transaction_data' => [],
                'status_history' => [],
                'refund_availability' => 'none',
                'refundable_amount' => 0,
                'platform' => 'test',
                'product' => 'chip',
                'send_receipt' => false,
                'is_test' => true,
                'is_recurring_token' => false,
                'skip_capture' => false,
                'force_recurring' => false,
                'marked_as_paid' => false,
            ], $overrides));
        }

        /**
         * Create enriched payload with a local purchase
         *
         * @param  array<string, mixed>  $rawPayload
         */
        function createPayloadWithPurchase(
            string $event,
            AIArmada\Chip\Models\Purchase $purchase,
            array $rawPayload = []
        ): EnrichedWebhookPayload {
            $payload = array_merge([
                'id' => $purchase->id,
                'type' => 'purchase',
                'status' => $rawPayload['status'] ?? 'paid',
                'is_test' => true,
                'client_id' => 'client-123',
                'brand_id' => $purchase->brand_id,
                'created_on' => time(),
                'updated_on' => time(),
                'client' => ['email' => 'test@example.com'],
                'purchase' => ['total' => 10000, 'currency' => 'MYR'],
            ], $rawPayload);

            return new EnrichedWebhookPayload(
                event: $event,
                rawPayload: $payload,
                localPurchase: $purchase,
                owner: null,
                receivedAt: now(),
                purchaseId: $purchase->id,
                clientId: 'client-123',
            );
        }

        it('PurchasePaidHandler updates purchase status to paid', function (): void {
            Illuminate\Support\Facades\Event::fake();

            $purchase = createTestPurchase(['status' => 'created']);
            $payload = createPayloadWithPurchase('purchase.paid', $purchase, ['status' => 'paid']);

            $handler = new PurchasePaidHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            $purchase->refresh();
            expect($purchase->status->value ?? $purchase->status)->toBe('paid');

            Illuminate\Support\Facades\Event::assertDispatched(AIArmada\Chip\Events\PurchasePaid::class);
        });

        it('PurchaseCancelledHandler updates purchase status to cancelled', function (): void {
            Illuminate\Support\Facades\Event::fake();

            $purchase = createTestPurchase(['status' => 'created']);
            $payload = createPayloadWithPurchase('purchase.cancelled', $purchase, ['status' => 'cancelled']);

            $handler = new PurchaseCancelledHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            $purchase->refresh();
            expect($purchase->status->value ?? $purchase->status)->toBe('cancelled');

            Illuminate\Support\Facades\Event::assertDispatched(AIArmada\Chip\Events\PurchaseCancelled::class);
        });

        it('PaymentFailedHandler updates purchase status', function (): void {
            Illuminate\Support\Facades\Event::fake();

            $purchase = createTestPurchase(['status' => 'pending']);
            $payload = createPayloadWithPurchase('purchase.payment_failure', $purchase, ['status' => 'failed']);

            $handler = new PaymentFailedHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            Illuminate\Support\Facades\Event::assertDispatched(AIArmada\Chip\Events\PurchasePaymentFailure::class);
        });

        it('PurchaseRefundedHandler updates purchase', function (): void {
            Illuminate\Support\Facades\Event::fake();

            $purchase = createTestPurchase(['status' => 'paid']);
            $payload = createPayloadWithPurchase('payment.refunded', $purchase, [
                'status' => 'refunded',
                'refund_amount' => 10000, // Required for the handler
            ]);

            $handler = new PurchaseRefundedHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            Illuminate\Support\Facades\Event::assertDispatched(AIArmada\Chip\Events\PaymentRefunded::class);
        });

        it('SendCompletedHandler updates send instruction state', function (): void {
            Illuminate\Support\Facades\Event::fake();

            $instruction = AIArmada\Chip\Models\SendInstruction::create([
                'id' => 12345,
                'bank_account_id' => 1,
                'amount' => '100.00',
                'email' => 'test@example.com',
                'description' => 'Test Payout',
                'reference' => 'ref-123',
                'state' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payload = new EnrichedWebhookPayload(
                event: 'payout.success',
                rawPayload: [
                    'id' => 12345,
                    'type' => 'payout',
                    'status' => 'success',
                ],
                localPurchase: null, // Payouts don't have localPurchase attached in this context usually, or do they?
                // The handler looks up instruction manually.
                owner: null,
                receivedAt: now(),
                purchaseId: '12345',
                clientId: 'client-123',
            );

            $handler = new SendCompletedHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            $instruction->refresh();
            expect($instruction->state)->toBe(AIArmada\Chip\Enums\SendInstructionState::COMPLETED->value);

            Illuminate\Support\Facades\Event::assertDispatched(AIArmada\Chip\Events\PayoutSuccess::class);
        });

        it('SendRejectedHandler updates send instruction state', function (): void {
            Illuminate\Support\Facades\Event::fake();

            $instruction = AIArmada\Chip\Models\SendInstruction::create([
                'id' => 67890,
                'bank_account_id' => 1,
                'amount' => '100.00',
                'email' => 'test@example.com',
                'description' => 'Test Payout',
                'reference' => 'ref-456',
                'state' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payload = new EnrichedWebhookPayload(
                event: 'payout.failed',
                rawPayload: [
                    'id' => 67890,
                    'type' => 'payout',
                    'status' => 'failed',
                    'failure_reason' => 'insufficient_funds',
                ],
                localPurchase: null,
                owner: null,
                receivedAt: now(),
                purchaseId: '67890',
                clientId: 'client-123',
            );

            $handler = new SendRejectedHandler;
            $result = $handler->handle($payload);

            expect($result)->toBeInstanceOf(WebhookResult::class);
            expect($result->isHandled())->toBeTrue();

            $instruction->refresh();
            expect($instruction->state)->toBe(AIArmada\Chip\Enums\SendInstructionState::REJECTED->value);

            Illuminate\Support\Facades\Event::assertDispatched(AIArmada\Chip\Events\PayoutFailed::class);
        });
    });
});
