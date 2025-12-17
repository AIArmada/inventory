<?php

declare(strict_types=1);

use AIArmada\Chip\Webhooks\ChipWebhookProfile;
use AIArmada\Chip\Webhooks\WebhookLogger;
use AIArmada\Chip\Webhooks\WebhookValidator;
use Illuminate\Http\Request;

describe('ChipWebhookProfile', function (): void {
    it('can be instantiated', function (): void {
        $profile = new ChipWebhookProfile;
        expect($profile)->toBeInstanceOf(ChipWebhookProfile::class);
    });

    it('returns false when event_type is missing', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', []);

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('returns false for empty event_type', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', ['event_type' => '']);

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('returns true for purchase events', function (): void {
        $profile = new ChipWebhookProfile;

        $events = [
            'purchase.created',
            'purchase.paid',
            'purchase.cancelled',
            'purchase.payment_failure',
        ];

        foreach ($events as $event) {
            $request = Request::create('/webhook', 'POST', ['event_type' => $event]);
            expect($profile->shouldProcess($request))->toBeTrue("Failed for {$event}");
        }
    });

    it('returns true for payment events', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', ['event_type' => 'payment.refunded']);

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('returns true for payout events', function (): void {
        $profile = new ChipWebhookProfile;

        $events = [
            'payout.pending',
            'payout.success',
            'payout.failed',
        ];

        foreach ($events as $event) {
            $request = Request::create('/webhook', 'POST', ['event_type' => $event]);
            expect($profile->shouldProcess($request))->toBeTrue("Failed for {$event}");
        }
    });

    it('returns true for billing_template_client events', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', [
            'event_type' => 'billing_template_client.subscription_billing_cancelled',
        ]);

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('returns false for unknown event types', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', ['event_type' => 'unknown.event']);

        expect($profile->shouldProcess($request))->toBeFalse();
    });
});

describe('WebhookValidator', function (): void {
    it('can be instantiated', function (): void {
        $validator = new WebhookValidator;
        expect($validator)->toBeInstanceOf(WebhookValidator::class);
    });

    it('returns false when signature header is missing', function (): void {
        $keyPair = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        expect($keyPair)->not->toBeFalse();

        openssl_pkey_export($keyPair, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($keyPair);

        expect($publicKeyDetails)->toBeArray()
            ->and($publicKeyDetails)->toHaveKey('key');

        config([
            'chip.webhooks.verify_signature' => true,
            'chip.webhooks.company_public_key' => $publicKeyDetails['key'],
        ]);

        $validator = new WebhookValidator;
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{"test":"data"}');

        expect($validator->validate($request))->toBeFalse();
    });

    it('returns false when company public key is not configured', function (): void {
        config([
            'chip.webhooks.verify_signature' => true,
            'chip.webhooks.company_public_key' => null,
        ]);

        $validator = new WebhookValidator;
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => 'some-signature',
        ], '{"test":"data"}');

        expect($validator->validate($request))->toBeFalse();
    });

    it('returns false for invalid signature', function (): void {
        $keyPair = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        expect($keyPair)->not->toBeFalse();

        $publicKeyDetails = openssl_pkey_get_details($keyPair);
        expect($publicKeyDetails)->toBeArray()
            ->and($publicKeyDetails)->toHaveKey('key');

        config([
            'chip.webhooks.verify_signature' => true,
            'chip.webhooks.company_public_key' => $publicKeyDetails['key'],
        ]);

        $validator = new WebhookValidator;
        $payload = '{"test":"data"}';
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => base64_encode('definitely-not-a-real-signature'),
        ], $payload);

        expect($validator->validate($request))->toBeFalse();
    });

    it('returns true for valid signature', function (): void {
        $keyPair = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        expect($keyPair)->not->toBeFalse();

        openssl_pkey_export($keyPair, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($keyPair);

        expect($publicKeyDetails)->toBeArray()
            ->and($publicKeyDetails)->toHaveKey('key');

        $payload = '{"test":"data"}';
        $signature = '';
        $signed = openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        expect($signed)->toBeTrue();

        config([
            'chip.webhooks.verify_signature' => true,
            'chip.webhooks.company_public_key' => $publicKeyDetails['key'],
        ]);

        $validator = new WebhookValidator;
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => base64_encode($signature),
        ], $payload);

        expect($validator->validate($request))->toBeTrue();
    });
});

describe('WebhookLogger', function (): void {
    it('can be instantiated', function (): void {
        $logger = new WebhookLogger;
        expect($logger)->toBeInstanceOf(WebhookLogger::class);
    });

    it('generates idempotency key from payload', function (): void {
        $logger = new WebhookLogger;

        $payload = [
            'event_type' => 'purchase.paid',
            'id' => 'purch_123',
            'created_on' => 1705300800,
        ];

        $key = $logger->generateIdempotencyKey($payload);

        expect($key)->toBeString()
            ->and(mb_strlen($key))->toBe(64); // SHA256 hash length
    });

    it('generates consistent idempotency key for same payload', function (): void {
        $logger = new WebhookLogger;

        $payload = [
            'event_type' => 'purchase.paid',
            'id' => 'purch_123',
            'created_on' => 1705300800,
        ];

        $key1 = $logger->generateIdempotencyKey($payload);
        $key2 = $logger->generateIdempotencyKey($payload);

        expect($key1)->toBe($key2);
    });

    it('generates different keys for different payloads', function (): void {
        $logger = new WebhookLogger;

        $payload1 = [
            'event_type' => 'purchase.paid',
            'id' => 'purch_123',
            'created_on' => 1705300800,
        ];

        $payload2 = [
            'event_type' => 'purchase.paid',
            'id' => 'purch_456',
            'created_on' => 1705300800,
        ];

        $key1 = $logger->generateIdempotencyKey($payload1);
        $key2 = $logger->generateIdempotencyKey($payload2);

        expect($key1)->not->toBe($key2);
    });

    it('handles nested data structure for idempotency key', function (): void {
        $logger = new WebhookLogger;

        $payload = [
            'event' => 'purchase.paid',
            'data' => [
                'id' => 'purch_nested',
            ],
            'created' => '2024-01-15T10:00:00Z',
        ];

        $key = $logger->generateIdempotencyKey($payload);

        expect($key)->toBeString()
            ->and(mb_strlen($key))->toBe(64);
    });

    it('logs invalid signature warning', function (): void {
        $logger = new WebhookLogger;
        $request = Request::create('/webhook', 'POST');

        // Just verify it doesn't throw (actual log output not tested)
        $logger->logInvalidSignature($request);
        expect(true)->toBeTrue();
    });
});
