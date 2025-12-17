<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\Chip\Gateways\ChipPaymentIntent;
use AIArmada\Chip\Gateways\ChipWebhookHandler;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;
use Akaunting\Money\Money;

describe('ChipGateway', function (): void {
    beforeEach(function (): void {
        $this->collectService = Mockery::mock(ChipCollectService::class);
        $this->webhookService = Mockery::mock(WebhookService::class);
        $this->gateway = new ChipGateway($this->collectService, $this->webhookService);
    });

    it('implements PaymentGatewayInterface', function (): void {
        expect($this->gateway)->toBeInstanceOf(PaymentGatewayInterface::class);
    });

    describe('getName', function (): void {
        it('returns chip', function (): void {
            expect($this->gateway->getName())->toBe('chip');
        });
    });

    describe('getDisplayName', function (): void {
        it('returns CHIP', function (): void {
            expect($this->gateway->getDisplayName())->toBe('CHIP');
        });
    });

    describe('isTestMode', function (): void {
        it('returns true when environment is sandbox', function (): void {
            config(['chip.environment' => 'sandbox']);
            expect($this->gateway->isTestMode())->toBeTrue();
        });

        it('returns false when environment is production', function (): void {
            config(['chip.environment' => 'production']);
            expect($this->gateway->isTestMode())->toBeFalse();
        });
    });

    describe('supports', function (): void {
        it('supports refunds', function (): void {
            expect($this->gateway->supports('refunds'))->toBeTrue();
        });

        it('supports partial_refunds', function (): void {
            expect($this->gateway->supports('partial_refunds'))->toBeTrue();
        });

        it('supports pre_authorization', function (): void {
            expect($this->gateway->supports('pre_authorization'))->toBeTrue();
        });

        it('supports recurring', function (): void {
            expect($this->gateway->supports('recurring'))->toBeTrue();
        });

        it('supports webhooks', function (): void {
            expect($this->gateway->supports('webhooks'))->toBeTrue();
        });

        it('supports hosted_checkout', function (): void {
            expect($this->gateway->supports('hosted_checkout'))->toBeTrue();
        });

        it('does not support embedded_checkout', function (): void {
            expect($this->gateway->supports('embedded_checkout'))->toBeFalse();
        });

        it('supports direct_charge', function (): void {
            expect($this->gateway->supports('direct_charge'))->toBeTrue();
        });

        it('returns false for unknown features', function (): void {
            expect($this->gateway->supports('unknown_feature'))->toBeFalse();
        });
    });

    describe('getWebhookHandler', function (): void {
        it('returns a ChipWebhookHandler', function (): void {
            $handler = $this->gateway->getWebhookHandler();
            expect($handler)->toBeInstanceOf(WebhookHandlerInterface::class);
            expect($handler)->toBeInstanceOf(ChipWebhookHandler::class);
        });
    });

    describe('getPayment', function (): void {
        it('returns payment intent by ID', function (): void {
            $purchaseData = PurchaseData::from([
                'id' => 'purchase-123',
                'type' => 'purchase',
                'status' => 'paid',
                'is_test' => true,
                'client_id' => 'client-123',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $this->collectService->shouldReceive('getPurchase')
                ->with('purchase-123')
                ->andReturn($purchaseData);

            $result = $this->gateway->getPayment('purchase-123');

            expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
            expect($result->getPaymentId())->toBe('purchase-123');
        });

        it('throws PaymentGatewayException when not found', function (): void {
            $this->collectService->shouldReceive('getPurchase')
                ->andThrow(new \Exception('Not found'));

            expect(fn() => $this->gateway->getPayment('invalid-id'))
                ->toThrow(PaymentGatewayException::class);
        });
    });

    describe('cancelPayment', function (): void {
        it('cancels a payment by ID', function (): void {
            $purchaseData = PurchaseData::from([
                'id' => 'purchase-123',
                'type' => 'purchase',
                'status' => 'cancelled',
                'is_test' => true,
                'client_id' => 'client-123',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $this->collectService->shouldReceive('cancelPurchase')
                ->with('purchase-123')
                ->andReturn($purchaseData);

            $result = $this->gateway->cancelPayment('purchase-123');

            expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        });

        it('throws PaymentGatewayException on failure', function (): void {
            $this->collectService->shouldReceive('cancelPurchase')
                ->andThrow(new \Exception('Cannot cancel'));

            expect(fn() => $this->gateway->cancelPayment('purchase-123'))
                ->toThrow(PaymentGatewayException::class);
        });
    });

    describe('refundPayment', function (): void {
        it('refunds a payment by ID with full amount', function (): void {
            $purchaseData = PurchaseData::from([
                'id' => 'purchase-123',
                'type' => 'purchase',
                'status' => 'refunded',
                'is_test' => true,
                'client_id' => 'client-123',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $this->collectService->shouldReceive('refundPurchase')
                ->with('purchase-123', null)
                ->andReturn($purchaseData);

            $result = $this->gateway->refundPayment('purchase-123');

            expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        });

        it('refunds a payment with partial amount', function (): void {
            $purchaseData = PurchaseData::from([
                'id' => 'purchase-123',
                'type' => 'purchase',
                'status' => 'refunded',
                'is_test' => true,
                'client_id' => 'client-123',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $amount = Money::MYR(5000);

            $this->collectService->shouldReceive('refundPurchase')
                ->with('purchase-123', 5000)
                ->andReturn($purchaseData);

            $result = $this->gateway->refundPayment('purchase-123', $amount);

            expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        });

        it('throws PaymentGatewayException on failure', function (): void {
            $this->collectService->shouldReceive('refundPurchase')
                ->andThrow(new \Exception('Cannot refund'));

            expect(fn() => $this->gateway->refundPayment('purchase-123'))
                ->toThrow(PaymentGatewayException::class);
        });
    });

    describe('capturePayment', function (): void {
        it('captures a preauthorized payment', function (): void {
            $purchaseData = PurchaseData::from([
                'id' => 'purchase-123',
                'type' => 'purchase',
                'status' => 'paid',
                'is_test' => true,
                'client_id' => 'client-123',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $this->collectService->shouldReceive('capturePurchase')
                ->with('purchase-123', null)
                ->andReturn($purchaseData);

            $result = $this->gateway->capturePayment('purchase-123');

            expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        });

        it('captures a payment with specific amount', function (): void {
            $purchaseData = PurchaseData::from([
                'id' => 'purchase-123',
                'type' => 'purchase',
                'status' => 'paid',
                'is_test' => true,
                'client_id' => 'client-123',
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $amount = Money::MYR(7500);

            $this->collectService->shouldReceive('capturePurchase')
                ->with('purchase-123', 7500)
                ->andReturn($purchaseData);

            $result = $this->gateway->capturePayment('purchase-123', $amount);

            expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        });

        it('throws PaymentGatewayException on failure', function (): void {
            $this->collectService->shouldReceive('capturePurchase')
                ->andThrow(new \Exception('Cannot capture'));

            expect(fn() => $this->gateway->capturePayment('purchase-123'))
                ->toThrow(PaymentGatewayException::class);
        });
    });

    describe('getPaymentMethods', function (): void {
        it('returns payment methods from service', function (): void {
            $methods = [
                ['code' => 'fpx', 'name' => 'FPX'],
                ['code' => 'card', 'name' => 'Credit/Debit Card'],
            ];

            $this->collectService->shouldReceive('getPaymentMethods')
                ->with([])
                ->andReturn($methods);

            $result = $this->gateway->getPaymentMethods();

            expect($result)->toBe($methods);
        });

        it('passes filters to service', function (): void {
            $filters = ['currency' => 'MYR'];
            $methods = [['code' => 'fpx', 'name' => 'FPX']];

            $this->collectService->shouldReceive('getPaymentMethods')
                ->with($filters)
                ->andReturn($methods);

            $result = $this->gateway->getPaymentMethods($filters);

            expect($result)->toBe($methods);
        });
    });
});
