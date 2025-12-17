<?php

declare(strict_types=1);

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Listeners\StoreWebhookData;
use AIArmada\Chip\Models\Purchase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

describe('StoreWebhookData Listener', function (): void {
    beforeEach(function (): void {
        $this->listener = new StoreWebhookData;
        Config::set('chip.webhooks.store_data', true);
    });

    describe('configuration checks', function (): void {
        it('does nothing when store_data config is false', function (): void {
            Config::set('chip.webhooks.store_data', false);

            $event = WebhookReceived::fromPayload([
                'id' => 'purchase-test-id-1',
                'type' => 'purchase',
                'status' => 'paid',
                'event_type' => 'purchase.paid',
                'brand_id' => 'brand-123',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            // Mock the Purchase model to verify it's NOT called
            Purchase::shouldReceive('updateOrCreate')->never();

            $this->listener->handle($event);
        })->skip('Mockery static mocking conflicts with Eloquent');

        it('only processes purchase type webhooks', function (): void {
            $initialPurchaseCount = Purchase::count();

            $event = WebhookReceived::fromPayload([
                'id' => 'payout-123',
                'type' => 'payout',  // Not a purchase
                'status' => 'success',
                'event_type' => 'payout.success',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $this->listener->handle($event);

            // No purchase should be created for payout type
            expect(Purchase::count())->toBe($initialPurchaseCount);
            expect(Purchase::find('payout-123'))->toBeNull();
        });

        it('skips when no id in payload', function (): void {
            Log::shouldReceive('warning')
                ->once()
                ->with('CHIP: No purchase ID in webhook payload');

            // Use eventType directly in constructor to bypass PurchaseData validation
            $event = new WebhookReceived(
                eventType: 'purchase.paid',
                payload: [
                    'type' => 'purchase',
                    'status' => 'paid',
                    'created_on' => time(),
                    'updated_on' => time(),
                ],
            );

            $initialCount = Purchase::count();
            $this->listener->handle($event);

            expect(Purchase::count())->toBe($initialCount);
        });

        it('skips when type is not purchase', function (): void {
            $event = new WebhookReceived(
                eventType: 'billing_template.created',
                payload: [
                    'id' => 'billing-template-123',
                    'type' => 'billing_template',
                    'status' => 'active',
                    'created_on' => time(),
                    'updated_on' => time(),
                ],
            );

            $initialCount = Purchase::count();
            $this->listener->handle($event);

            expect(Purchase::count())->toBe($initialCount);
        });
    });

    describe('store_data config toggle', function (): void {
        it('returns early when store_data is false', function (): void {
            Config::set('chip.webhooks.store_data', false);

            $event = new WebhookReceived(
                eventType: 'purchase.paid',
                payload: [
                    'id' => 'purchase-config-test',
                    'type' => 'purchase',
                    'status' => 'paid',
                    'brand_id' => 'brand-123',
                    'created_on' => time(),
                    'updated_on' => time(),
                ],
            );

            $initialCount = Purchase::count();
            $this->listener->handle($event);

            expect(Purchase::count())->toBe($initialCount);
            expect(Purchase::find('purchase-config-test'))->toBeNull();
        });
    });

    describe('listener instantiation', function (): void {
        it('can be instantiated', function (): void {
            $listener = new StoreWebhookData;
            expect($listener)->toBeInstanceOf(StoreWebhookData::class);
        });

        it('has handle method', function (): void {
            expect(method_exists($this->listener, 'handle'))->toBeTrue();
        });

        it('handle method accepts WebhookReceived event', function (): void {
            $reflection = new ReflectionMethod($this->listener, 'handle');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()?->getName())->toBe(WebhookReceived::class);
        });
    });
});
