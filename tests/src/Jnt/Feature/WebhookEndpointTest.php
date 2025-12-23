<?php

declare(strict_types=1);

use AIArmada\Jnt\Services\WebhookService;
use AIArmada\Jnt\Webhooks\ProcessJntWebhook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Spatie\WebhookClient\Models\WebhookCall;

describe('Webhook Endpoint', function (): void {
    beforeEach(function (): void {
        $this->privateKey = 'test-private-key-12345';
        config(['jnt.private_key' => $this->privateKey]);

        // Bind WebhookService with test private key
        $this->app->singleton(WebhookService::class, fn (): WebhookService => new WebhookService($this->privateKey));
    });

    it('accepts valid webhook with correct signature', function (): void {
        Queue::fake();

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'txlogisticId' => 'ORDER-001',
            'details' => [
                [
                    'scanTime' => '2024-01-15 10:30:00',
                    'desc' => 'Package collected',
                    'scanTypeCode' => 'CC',
                    'scanTypeName' => 'Collection',
                    'scanType' => 'collection',
                    'scanNetworkId' => 100001,
                    'scanNetworkName' => 'KL Hub',
                ],
            ],
        ]);

        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        $response->assertOk()
            ->assertJson([
                'code' => '1',
                'msg' => 'success',
                'data' => 'SUCCESS',
            ])
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
                'requestId',
            ]);

        Queue::assertPushed(ProcessJntWebhook::class);
        expect(WebhookCall::query()->where('name', 'jnt.webhooks.status')->count())->toBe(1);
    });

    it('rejects webhook with invalid signature', function (): void {
        Queue::fake();

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'details' => [],
        ]);

        $invalidSignature = 'invalid-signature-xyz';

        $response = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $invalidSignature,
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'code' => '0',
                'msg' => 'Invalid signature',
            ]);

        Queue::assertNothingPushed();
    });

    it('rejects webhook without digest header', function (): void {
        Queue::fake();

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'details' => [],
        ]);

        $response = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'code' => '0',
                'msg' => 'Invalid signature',
            ]);

        Queue::assertNothingPushed();
    });

    it('rejects webhook with missing bizContent', function (): void {
        Queue::fake();

        // Even with a valid signature, missing bizContent fails signature verification
        // because signature verification happens in middleware before controller
        $bizContent = '';
        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response = $this->postJson('/webhooks/jnt/status', [], [
            'digest' => $signature,
        ]);

        // Signature verification fails because bizContent is empty
        $response->assertUnauthorized()
            ->assertJson([
                'code' => '0',
                'msg' => 'Invalid signature',
            ]);

        Queue::assertNothingPushed();
    });

    it('rejects webhook with invalid JSON in bizContent', function (): void {
        Queue::fake();

        $bizContent = 'invalid-json';
        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => '0',
                'msg' => 'Invalid payload',
            ]);

        Queue::assertNothingPushed();
    });

    it('rejects webhook with missing billCode in bizContent', function (): void {
        Queue::fake();

        $bizContent = json_encode([
            'details' => [],
        ]);

        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => '0',
                'msg' => 'Invalid payload',
            ]);

        Queue::assertNothingPushed();
    });

    it('handles multiple tracking details correctly', function (): void {
        Queue::fake();

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'txlogisticId' => 'ORDER-001',
            'details' => [
                [
                    'scanTime' => '2024-01-15 09:00:00',
                    'desc' => 'Package collected',
                    'scanTypeCode' => 'CC',
                    'scanTypeName' => 'Collection',
                    'scanType' => 'collection',
                    'scanNetworkName' => 'KL Hub',
                ],
                [
                    'scanTime' => '2024-01-15 14:00:00',
                    'desc' => 'In transit',
                    'scanTypeCode' => 'IT',
                    'scanTypeName' => 'In Transit',
                    'scanType' => 'dispatch',
                    'scanNetworkName' => 'Penang Hub',
                ],
                [
                    'scanTime' => '2024-01-16 10:00:00',
                    'desc' => 'Out for delivery',
                    'scanTypeCode' => 'OD',
                    'scanTypeName' => 'Out for Delivery',
                    'scanType' => '派件',
                    'scanNetworkName' => 'Penang Branch',
                ],
            ],
        ]);

        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        $response->assertOk()
            ->assertJson([
                'code' => '1',
                'msg' => 'success',
            ]);

        Queue::assertPushed(ProcessJntWebhook::class);

        $call = WebhookCall::query()->where('name', 'jnt.webhooks.status')->latest('id')->firstOrFail();
        $decoded = json_decode((string) ($call->payload['bizContent'] ?? ''), true);

        expect($decoded['billCode'] ?? null)->toBe('JNTMY12345678');
        expect($decoded['details'] ?? null)->toBeArray();
        expect(count($decoded['details']))->toBe(3);
        expect($decoded['details'][2]['scanType'] ?? null)->toBe('派件');
    });

    it('logs webhook reception when logging is enabled', function (): void {
        Log::spy();
        Queue::fake();

        config(['jnt.webhooks.log_payloads' => true]);

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'txlogisticId' => 'ORDER-001',
            'details' => [
                [
                    'scanTime' => '2024-01-15 10:30:00',
                    'desc' => 'Package collected',
                    'scanTypeCode' => 'CC',
                    'scanTypeName' => 'Collection',
                    'scanType' => 'collection',
                ],
            ],
        ]);

        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('J&T webhook received', Mockery::on(fn ($context): bool => $context['billCode'] === 'JNTMY12345678'
                && $context['txlogisticId'] === 'ORDER-001'
                && $context['detailsCount'] === 1));
    });

    it('does not log webhook when logging is disabled', function (): void {
        Log::spy();
        Queue::fake();

        config(['jnt.webhooks.log_payloads' => false]);

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'details' => [
                [
                    'scanTime' => '2024-01-15 10:30:00',
                    'desc' => 'Package collected',
                    'scanTypeCode' => 'CC',
                    'scanTypeName' => 'Collection',
                    'scanType' => 'collection',
                ],
            ],
        ]);

        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        Log::shouldNotHaveReceived('info');
    });

    it('logs warnings for signature verification failures', function (): void {
        Log::spy();
        Queue::fake();

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'details' => [],
        ]);

        $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => 'invalid-signature',
        ]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('J&T webhook signature verification failed', Mockery::any());
    });

    it('handles webhook with optional txlogisticId', function (): void {
        Queue::fake();

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'details' => [
                [
                    'scanTime' => '2024-01-15 10:30:00',
                    'desc' => 'Package collected',
                    'scanTypeCode' => 'CC',
                    'scanTypeName' => 'Collection',
                    'scanType' => 'collection',
                ],
            ],
        ]);

        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        $response->assertOk();

        Queue::assertPushed(ProcessJntWebhook::class);

        $call = WebhookCall::query()->where('name', 'jnt.webhooks.status')->latest('id')->firstOrFail();
        $decoded = json_decode((string) ($call->payload['bizContent'] ?? ''), true);

        expect($decoded['txlogisticId'] ?? null)->toBeNull();
    });

    it('returns unique requestId for each webhook', function (): void {
        Queue::fake();

        $bizContent = json_encode([
            'billCode' => 'JNTMY12345678',
            'details' => [
                [
                    'scanTime' => '2024-01-15 10:30:00',
                    'desc' => 'Package collected',
                    'scanTypeCode' => 'CC',
                    'scanTypeName' => 'Collection',
                    'scanType' => 'collection',
                ],
            ],
        ]);

        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response1 = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        $response2 = $this->postJson('/webhooks/jnt/status', [
            'bizContent' => $bizContent,
        ], [
            'digest' => $signature,
        ]);

        $requestId1 = $response1->json('requestId');
        $requestId2 = $response2->json('requestId');

        expect($requestId1)->not->toBe($requestId2);
    });
});
