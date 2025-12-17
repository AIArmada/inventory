<?php

declare(strict_types=1);

use AIArmada\Chip\Gateways\ChipPaymentIntent;
use AIArmada\Chip\Gateways\ChipWebhookHandler;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;
use Illuminate\Http\Request;

function createWebhookRequest(array $payload): Request
{
    $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode($payload));
    $request->headers->set('Content-Type', 'application/json');

    return $request;
}

describe('ChipWebhookHandler instantiation', function (): void {
    it('can be instantiated with dependencies', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);

        expect($handler)->toBeInstanceOf(ChipWebhookHandler::class);
    });

    it('implements WebhookHandlerInterface', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);

        expect($handler)->toBeInstanceOf(WebhookHandlerInterface::class);
    });
});

describe('ChipWebhookHandler verifyWebhook', function (): void {
    it('returns true when signature is valid', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andReturn(true);

        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        $result = $handler->verifyWebhook($request);

        expect($result)->toBeTrue();
    });

    it('returns false when signature is invalid', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andReturn(false);

        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        $result = $handler->verifyWebhook($request);

        expect($result)->toBeFalse();
    });

    it('throws WebhookVerificationException when verification fails', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('verifySignature')
            ->once()
            ->andThrow(new \AIArmada\Chip\Exceptions\WebhookVerificationException('Invalid signature'));

        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        expect(fn() => $handler->verifyWebhook($request))
            ->toThrow(WebhookVerificationException::class);
    });
});

describe('ChipWebhookHandler getEventType', function (): void {
    it('returns payment.paid for paid status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.paid');
    });

    it('returns payment.refunded for refunded status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'refunded']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.refunded');
    });

    it('returns payment.cancelled for cancelled status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'cancelled']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.cancelled');
    });

    it('returns payment.failed for error status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'error']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.failed');
    });

    it('returns payment.failed for blocked status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'blocked']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.failed');
    });

    it('returns payment.authorized for hold status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'hold']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.authorized');
    });

    it('returns payment.authorized for preauthorized status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'preauthorized']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.authorized');
    });

    it('returns payment.pending for pending_execute status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'pending_execute']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.pending');
    });

    it('returns payment.pending for pending_charge status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'pending_charge']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.pending');
    });

    it('returns refund.pending for pending_refund status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'pending_refund']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('refund.pending');
    });

    it('returns unknown for invalid JSON', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = Request::create('/webhook', 'POST', [], [], [], [], 'invalid json');

        $result = $handler->getEventType($request);

        expect($result)->toBe('unknown');
    });

    it('returns payment.unknown for missing status', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['id' => 'test']);

        $result = $handler->getEventType($request);

        expect($result)->toBe('payment.unknown');
    });
});

describe('ChipWebhookHandler isPaymentEvent', function (): void {
    it('always returns true for CHIP webhooks', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        $result = $handler->isPaymentEvent($request);

        expect($result)->toBeTrue();
    });
});

describe('ChipWebhookHandler getPaymentFromWebhook', function (): void {
    it('returns null for invalid JSON', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = Request::create('/webhook', 'POST', [], [], [], [], 'invalid');

        $result = $handler->getPaymentFromWebhook($request);

        expect($result)->toBeNull();
    });

    it('returns null for payload without id', function (): void {
        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest(['status' => 'paid']);

        $result = $handler->getPaymentFromWebhook($request);

        expect($result)->toBeNull();
    });

    it('returns ChipPaymentIntent from valid payload', function (): void {
        $payload = [
            'id' => 'purchase-123',
            'type' => 'purchase',
            'event_type' => 'purchase.paid',
            'status' => 'paid',
            'is_test' => true,
            'client_id' => 'client-456',
            'brand_id' => 'brand-789',
            'created_on' => 1702819200,
            'updated_on' => 1702819200,
            'payment' => ['amount' => 10000, 'currency' => 'MYR'],
            'products' => [['name' => 'Test', 'price' => 10000, 'quantity' => 1]],
        ];

        $webhookService = Mockery::mock(WebhookService::class);
        $collectService = Mockery::mock(ChipCollectService::class);

        $handler = new ChipWebhookHandler($webhookService, $collectService);
        $request = createWebhookRequest($payload);

        $result = $handler->getPaymentFromWebhook($request);

        expect($result)->toBeInstanceOf(ChipPaymentIntent::class);
        expect($result->getPaymentId())->toBe('purchase-123');
    });
});
