<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Gateways\ChipPaymentIntent;
use AIArmada\Chip\Gateways\ChipWebhookHandler;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

describe('Chip Facade', function (): void {
    it('returns webhook URL', function (): void {
        Config::set('chip.webhooks.route', '/chip/webhook');

        $url = Chip::webhookUrl();

        expect($url)->toContain('/chip/webhook');
    });

    it('uses custom webhook route from config', function (): void {
        Config::set('chip.webhooks.route', '/custom/chip/hook');

        $url = Chip::webhookUrl();

        expect($url)->toContain('/custom/chip/hook');
    });

    it('returns facade accessor', function (): void {
        $class = new \ReflectionClass(Chip::class);
        $method = $class->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke(null);

        expect($accessor)->toBe(ChipCollectService::class);
    });
});

describe('ChipWebhookHandler', function (): void {
    beforeEach(function (): void {
        $this->webhookService = Mockery::mock(WebhookService::class);
        $this->collectService = Mockery::mock(ChipCollectService::class);
        $this->handler = new ChipWebhookHandler($this->webhookService, $this->collectService);
    });

    describe('getEventType', function (): void {
        it('returns payment.paid for paid status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'id' => 'purchase-123',
                'status' => 'paid',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.paid');
        });

        it('returns payment.refunded for refunded status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'refunded',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.refunded');
        });

        it('returns payment.cancelled for cancelled status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'cancelled',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.cancelled');
        });

        it('returns payment.failed for error status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'error',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.failed');
        });

        it('returns payment.failed for blocked status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'blocked',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.failed');
        });

        it('returns payment.authorized for hold status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'hold',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.authorized');
        });

        it('returns payment.authorized for preauthorized status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'preauthorized',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.authorized');
        });

        it('returns payment.pending for pending_execute status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'pending_execute',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.pending');
        });

        it('returns refund.pending for pending_refund status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'pending_refund',
            ]));

            expect($this->handler->getEventType($request))->toBe('refund.pending');
        });

        it('returns unknown for invalid JSON', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], 'not json');

            expect($this->handler->getEventType($request))->toBe('unknown');
        });

        it('returns default for unknown status', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'some_new_status',
            ]));

            expect($this->handler->getEventType($request))->toBe('payment.some_new_status');
        });
    });

    describe('isPaymentEvent', function (): void {
        it('always returns true', function (): void {
            $request = Request::create('/webhook', 'POST');

            expect($this->handler->isPaymentEvent($request))->toBeTrue();
        });
    });

    describe('getPaymentFromWebhook', function (): void {
        it('returns null for invalid JSON', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], 'not json');

            expect($this->handler->getPaymentFromWebhook($request))->toBeNull();
        });

        it('returns null for missing id', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'status' => 'paid',
            ]));

            expect($this->handler->getPaymentFromWebhook($request))->toBeNull();
        });

        it('returns ChipPaymentIntent for valid purchase data', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'id' => 'purchase-' . uniqid(),
                'type' => 'purchase',
                'status' => 'paid',
                'brand_id' => 'brand-123',
                'is_test' => true,
                'created_on' => time(),
                'updated_on' => time(),
                'client' => ['email' => 'test@example.com'],
                'purchase' => ['total' => 10000, 'currency' => 'MYR'],
            ]));

            $result = $this->handler->getPaymentFromWebhook($request);

            expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        });
    });

    describe('verifyWebhook', function (): void {
        it('returns true when signature is valid', function (): void {
            $request = Request::create('/webhook', 'POST');

            $this->webhookService->shouldReceive('verifySignature')
                ->once()
                ->with($request)
                ->andReturn(true);

            expect($this->handler->verifyWebhook($request))->toBeTrue();
        });

        it('returns false when signature is invalid', function (): void {
            $request = Request::create('/webhook', 'POST');

            $this->webhookService->shouldReceive('verifySignature')
                ->once()
                ->with($request)
                ->andReturn(false);

            expect($this->handler->verifyWebhook($request))->toBeFalse();
        });
    });

    describe('parseWebhook', function (): void {
        it('parses webhook payload correctly', function (): void {
            $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
                'id' => 'purchase-123',
                'status' => 'paid',
                'reference' => 'REF-123',
                'updated_on' => time(),
            ]));

            $this->webhookService->shouldReceive('parsePayload')
                ->once()
                ->andReturn((object) [
                    'id' => 'purchase-123',
                    'status' => 'paid',
                    'reference' => 'REF-123',
                    'updated_on' => time(),
                ]);

            $result = $this->handler->parseWebhook($request);

            expect($result->paymentId)->toBe('purchase-123');
            expect($result->reference)->toBe('REF-123');
            expect($result->gatewayName)->toBe('chip');
            expect($result->status)->toBe(PaymentStatus::PAID);
        });
    });
});

describe('ChipPaymentIntent', function (): void {
    /**
     * Create a minimal PurchaseData for testing
     */
    function createTestPurchaseData(array $overrides = []): PurchaseData
    {
        return PurchaseData::from(array_merge([
            'id' => 'purchase-' . uniqid(),
            'type' => 'purchase',
            'status' => 'paid',
            'brand_id' => 'brand-123',
            'is_test' => true,
            'created_on' => time(),
            'updated_on' => time(),
            'client' => ['email' => 'test@example.com'],
            'purchase' => ['total' => 10000, 'currency' => 'MYR'],
        ], $overrides));
    }

    it('can be instantiated from PurchaseData', function (): void {
        $purchase = createTestPurchaseData();
        $intent = new ChipPaymentIntent($purchase);

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
    });

    it('returns gateway name as chip', function (): void {
        $purchase = createTestPurchaseData();
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getGatewayName())->toBe('chip');
    });

    it('returns payment id', function (): void {
        $purchase = createTestPurchaseData(['id' => 'purchase-test-123']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getPaymentId())->toBe('purchase-test-123');
    });

    it('returns checkout url', function (): void {
        $purchase = createTestPurchaseData(['checkout_url' => 'https://checkout.chip.my/test']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getCheckoutUrl())->toBe('https://checkout.chip.my/test');
    });

    it('returns correct status for paid', function (): void {
        $purchase = createTestPurchaseData(['status' => 'paid']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getStatus())->toBe(PaymentStatus::PAID);
    });

    it('returns correct status for cancelled', function (): void {
        $purchase = createTestPurchaseData(['status' => 'cancelled']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getStatus())->toBe(PaymentStatus::CANCELLED);
    });

    it('returns correct status for refunded', function (): void {
        $purchase = createTestPurchaseData(['status' => 'refunded']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getStatus())->toBe(PaymentStatus::REFUNDED);
    });

    it('returns correct status for error', function (): void {
        $purchase = createTestPurchaseData(['status' => 'error']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getStatus())->toBe(PaymentStatus::FAILED);
    });

    it('returns correct status for pending_execute', function (): void {
        $purchase = createTestPurchaseData(['status' => 'pending_execute']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getStatus())->toBe(PaymentStatus::PENDING);
    });

    it('returns correct status for hold', function (): void {
        $purchase = createTestPurchaseData(['status' => 'hold']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getStatus())->toBe(PaymentStatus::AUTHORIZED);
    });

    it('returns amount as Money object', function (): void {
        $purchase = createTestPurchaseData([
            'purchase' => ['total' => 25000, 'currency' => 'MYR'],
        ]);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getAmount())->toBeInstanceOf(Akaunting\Money\Money::class);
    });

    it('returns isTest correctly', function (): void {
        $purchase = createTestPurchaseData(['is_test' => true]);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isTest())->toBeTrue();
    });

    it('returns metadata', function (): void {
        $purchase = createTestPurchaseData([
            'purchase' => ['total' => 10000, 'currency' => 'MYR', 'metadata' => ['order_id' => 123]],
        ]);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getMetadata())->toBe(['order_id' => 123]);
    });

    it('checks if paid', function (): void {
        $purchase = createTestPurchaseData(['status' => 'paid']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isPaid())->toBeTrue();
    });

    it('checks if not paid', function (): void {
        $purchase = createTestPurchaseData(['status' => 'pending_execute']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isPaid())->toBeFalse();
    });

    it('checks if failed', function (): void {
        $purchase = createTestPurchaseData(['status' => 'error']);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isFailed())->toBeTrue();
    });
});

describe('ChipGateway', function (): void {
    beforeEach(function (): void {
        $this->collectService = Mockery::mock(ChipCollectService::class);
        $this->webhookService = Mockery::mock(WebhookService::class);
        $this->gateway = new \AIArmada\Chip\Gateways\ChipGateway($this->collectService, $this->webhookService);
    });

    it('returns correct name', function (): void {
        expect($this->gateway->getName())->toBe('chip');
    });

    it('returns correct display name', function (): void {
        expect($this->gateway->getDisplayName())->toBe('CHIP');
    });

    it('checks test mode from config', function (): void {
        Config::set('chip.environment', 'sandbox');
        expect($this->gateway->isTestMode())->toBeTrue();

        Config::set('chip.environment', 'production');
        expect($this->gateway->isTestMode())->toBeFalse();
    });

    it('supports features correctly', function (): void {
        expect($this->gateway->supports('refunds'))->toBeTrue();
        expect($this->gateway->supports('partial_refunds'))->toBeTrue();
        expect($this->gateway->supports('pre_authorization'))->toBeTrue();
        expect($this->gateway->supports('recurring'))->toBeTrue();
        expect($this->gateway->supports('webhooks'))->toBeTrue();
        expect($this->gateway->supports('hosted_checkout'))->toBeTrue();
        expect($this->gateway->supports('direct_charge'))->toBeTrue();

        expect($this->gateway->supports('embedded_checkout'))->toBeFalse();
        expect($this->gateway->supports('unknown_feature'))->toBeFalse();
    });

    it('returns webhook handler', function (): void {
        expect($this->gateway->getWebhookHandler())->toBeInstanceOf(ChipWebhookHandler::class);
    });

    it('gets payment by id', function (): void {
        $purchase = createTestPurchaseData(['id' => 'purchase-123']);

        $this->collectService->shouldReceive('getPurchase')
            ->once()
            ->with('purchase-123')
            ->andReturn($purchase);

        $intent = $this->gateway->getPayment('purchase-123');

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
        expect($intent->getPaymentId())->toBe('purchase-123');
    });

    it('throws exception when getting non-existent payment', function (): void {
        $this->collectService->shouldReceive('getPurchase')
            ->once()
            ->with('purchase-404')
            ->andThrow(new Exception('Not found'));

        $this->gateway->getPayment('purchase-404');
    })->throws(\AIArmada\CommerceSupport\Exceptions\PaymentGatewayException::class);

    it('cancels payment', function (): void {
        $purchase = createTestPurchaseData(['id' => 'purchase-123', 'status' => 'cancelled']);

        $this->collectService->shouldReceive('cancelPurchase')
            ->once()
            ->with('purchase-123')
            ->andReturn($purchase);

        $intent = $this->gateway->cancelPayment('purchase-123');

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
        expect($intent->getStatus())->toBe(PaymentStatus::CANCELLED);
    });

    it('throws exception when cancellation fails', function (): void {
        $this->collectService->shouldReceive('cancelPurchase')
            ->once()
            ->with('purchase-123')
            ->andThrow(new Exception('Failed'));

        $this->gateway->cancelPayment('purchase-123');
    })->throws(\AIArmada\CommerceSupport\Exceptions\PaymentGatewayException::class);

    it('refunds payment with full amount', function (): void {
        $purchase = createTestPurchaseData(['id' => 'purchase-123', 'status' => 'refunded']);

        $this->collectService->shouldReceive('refundPurchase')
            ->once()
            ->with('purchase-123', null)
            ->andReturn($purchase);

        $intent = $this->gateway->refundPayment('purchase-123');

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
        expect($intent->getStatus())->toBe(PaymentStatus::REFUNDED);
    });

    it('refunds payment with partial amount', function (): void {
        $purchase = createTestPurchaseData(['id' => 'purchase-123', 'status' => 'refunded']);
        $amount = Akaunting\Money\Money::MYR(5000); // 50.00 MYR

        $this->collectService->shouldReceive('refundPurchase')
            ->once()
            ->with('purchase-123', 5000)
            ->andReturn($purchase);

        $intent = $this->gateway->refundPayment('purchase-123', $amount);

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
    });

    it('throws exception when refund fails', function (): void {
        $this->collectService->shouldReceive('refundPurchase')
            ->once()
            ->with('purchase-123', null)
            ->andThrow(new Exception('Failed'));

        $this->gateway->refundPayment('purchase-123');
    })->throws(\AIArmada\CommerceSupport\Exceptions\PaymentGatewayException::class);

    it('captures payment', function (): void {
        $purchase = createTestPurchaseData(['id' => 'purchase-123', 'status' => 'paid']);

        $this->collectService->shouldReceive('capturePurchase')
            ->once()
            ->with('purchase-123', null)
            ->andReturn($purchase);

        $intent = $this->gateway->capturePayment('purchase-123');

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
        expect($intent->getStatus())->toBe(PaymentStatus::PAID);
    });

    it('captures payment with amount', function (): void {
        $purchase = createTestPurchaseData(['id' => 'purchase-123', 'status' => 'paid']);
        $amount = Akaunting\Money\Money::MYR(5000);

        $this->collectService->shouldReceive('capturePurchase')
            ->once()
            ->with('purchase-123', 5000)
            ->andReturn($purchase);

        $intent = $this->gateway->capturePayment('purchase-123', $amount);

        expect($intent)->toBeInstanceOf(ChipPaymentIntent::class);
    });

    it('throws exception when capture fails', function (): void {
        $this->collectService->shouldReceive('capturePurchase')
            ->once()
            ->with('purchase-123', null)
            ->andThrow(new Exception('Failed'));

        $this->gateway->capturePayment('purchase-123');
    })->throws(\AIArmada\CommerceSupport\Exceptions\PaymentGatewayException::class);

    it('gets payment methods', function (): void {
        $methods = ['fpx' => true, 'card' => true];

        $this->collectService->shouldReceive('getPaymentMethods')
            ->once()
            ->with(['currency' => 'MYR'])
            ->andReturn($methods);

        expect($this->gateway->getPaymentMethods(['currency' => 'MYR']))->toBe($methods);
    });
});
