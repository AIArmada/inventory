<?php

declare(strict_types=1);

use AIArmada\Chip\Webhooks\ChipSignatureValidator;
use AIArmada\Chip\Webhooks\ChipWebhookProfile;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;

it('returns the correct signature header name', function (): void {
    $validator = new ChipSignatureValidator;

    $reflection = new ReflectionMethod($validator, 'getSignatureHeader');

    expect($reflection->invoke($validator))->toBe('X-Signature');
});

it('returns sha256 as the hash algorithm', function (): void {
    $validator = new ChipSignatureValidator;

    $reflection = new ReflectionMethod($validator, 'getHashAlgorithm');

    expect($reflection->invoke($validator))->toBe('sha256');
});

it('validates a correct signature', function (): void {
    $validator = new ChipSignatureValidator;
    $secret = 'test-secret-key';
    $payload = json_encode(['event_type' => 'purchase.paid', 'id' => 'test-123']);

    // Compute expected signature
    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    // Create mock request with signature
    $request = Request::create(
        uri: '/webhook/chip',
        method: 'POST',
        content: $payload,
    );
    $request->headers->set('X-Signature', $expectedSignature);
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'chip',
        'signing_secret' => $secret,
        'signature_header_name' => 'X-Signature',
        'signature_validator' => ChipSignatureValidator::class,
        'webhook_profile' => ChipWebhookProfile::class,
        'webhook_model' => Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => AIArmada\Chip\Webhooks\ProcessChipWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeTrue();
});

it('rejects an incorrect signature', function (): void {
    $validator = new ChipSignatureValidator;
    $secret = 'test-secret-key';
    $payload = json_encode(['event_type' => 'purchase.paid', 'id' => 'test-123']);

    // Create mock request with wrong signature
    $request = Request::create(
        uri: '/webhook/chip',
        method: 'POST',
        content: $payload,
    );
    $request->headers->set('X-Signature', 'invalid-signature');
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'chip',
        'signing_secret' => $secret,
        'signature_header_name' => 'X-Signature',
        'signature_validator' => ChipSignatureValidator::class,
        'webhook_profile' => ChipWebhookProfile::class,
        'webhook_model' => Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => AIArmada\Chip\Webhooks\ProcessChipWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeFalse();
});

it('rejects request without signature header', function (): void {
    $validator = new ChipSignatureValidator;
    $secret = 'test-secret-key';
    $payload = json_encode(['event_type' => 'purchase.paid', 'id' => 'test-123']);

    // Create mock request without signature
    $request = Request::create(
        uri: '/webhook/chip',
        method: 'POST',
        content: $payload,
    );
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'chip',
        'signing_secret' => $secret,
        'signature_header_name' => 'X-Signature',
        'signature_validator' => ChipSignatureValidator::class,
        'webhook_profile' => ChipWebhookProfile::class,
        'webhook_model' => Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => AIArmada\Chip\Webhooks\ProcessChipWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeFalse();
});

it('rejects request with empty signing secret', function (): void {
    $validator = new ChipSignatureValidator;
    $payload = json_encode(['event_type' => 'purchase.paid', 'id' => 'test-123']);

    // Create mock request with signature
    $request = Request::create(
        uri: '/webhook/chip',
        method: 'POST',
        content: $payload,
    );
    $request->headers->set('X-Signature', 'some-signature');
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config with empty secret
    $config = new WebhookConfig([
        'name' => 'chip',
        'signing_secret' => '',
        'signature_header_name' => 'X-Signature',
        'signature_validator' => ChipSignatureValidator::class,
        'webhook_profile' => ChipWebhookProfile::class,
        'webhook_model' => Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => AIArmada\Chip\Webhooks\ProcessChipWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeFalse();
});
