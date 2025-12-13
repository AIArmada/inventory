<?php

declare(strict_types=1);

use AIArmada\Jnt\Webhooks\JntSignatureValidator;
use AIArmada\Jnt\Webhooks\JntWebhookProfile;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;

it('returns the correct signature header name', function (): void {
    $validator = new JntSignatureValidator;

    $reflection = new ReflectionMethod($validator, 'getSignatureHeader');

    expect($reflection->invoke($validator))->toBe('X-JNT-Signature');
});

it('returns sha256 as the hash algorithm', function (): void {
    $validator = new JntSignatureValidator;

    $reflection = new ReflectionMethod($validator, 'getHashAlgorithm');

    expect($reflection->invoke($validator))->toBe('sha256');
});

it('validates a correct signature', function (): void {
    $validator = new JntSignatureValidator;
    $secret = 'jnt-test-secret';
    $payload = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    // Compute expected signature
    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    // Create mock request with signature
    $request = Request::create(
        uri: '/webhook/jnt',
        method: 'POST',
        content: $payload,
    );
    $request->headers->set('X-JNT-Signature', $expectedSignature);
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt',
        'signing_secret' => $secret,
        'signature_header_name' => 'X-JNT-Signature',
        'signature_validator' => JntSignatureValidator::class,
        'webhook_profile' => JntWebhookProfile::class,
        'webhook_model' => Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => AIArmada\Jnt\Webhooks\ProcessJntWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeTrue();
});

it('rejects an incorrect signature', function (): void {
    $validator = new JntSignatureValidator;
    $secret = 'jnt-test-secret';
    $payload = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    // Create mock request with wrong signature
    $request = Request::create(
        uri: '/webhook/jnt',
        method: 'POST',
        content: $payload,
    );
    $request->headers->set('X-JNT-Signature', 'invalid-signature');
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt',
        'signing_secret' => $secret,
        'signature_header_name' => 'X-JNT-Signature',
        'signature_validator' => JntSignatureValidator::class,
        'webhook_profile' => JntWebhookProfile::class,
        'webhook_model' => Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => AIArmada\Jnt\Webhooks\ProcessJntWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeFalse();
});

it('rejects request without signature header', function (): void {
    $validator = new JntSignatureValidator;
    $secret = 'jnt-test-secret';
    $payload = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    // Create mock request without signature
    $request = Request::create(
        uri: '/webhook/jnt',
        method: 'POST',
        content: $payload,
    );
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt',
        'signing_secret' => $secret,
        'signature_header_name' => 'X-JNT-Signature',
        'signature_validator' => JntSignatureValidator::class,
        'webhook_profile' => JntWebhookProfile::class,
        'webhook_model' => Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => AIArmada\Jnt\Webhooks\ProcessJntWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeFalse();
});
