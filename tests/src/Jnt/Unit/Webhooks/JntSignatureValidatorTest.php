<?php

declare(strict_types=1);

use AIArmada\Jnt\Webhooks\JntSignatureValidator;
use AIArmada\Jnt\Webhooks\JntWebhookProfile;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;

it('returns the correct signature header name', function (): void {
    $validator = new JntSignatureValidator;

    $reflection = new ReflectionMethod($validator, 'getSignatureHeader');

    expect($reflection->invoke($validator))->toBe('digest');
});

it('validates a correct signature', function (): void {
    $validator = new JntSignatureValidator;
    $secret = 'jnt-test-secret';
    $bizContent = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    expect($bizContent)->not->toBeFalse();

    // Compute expected signature
    $expectedSignature = base64_encode(md5($bizContent . $secret, true));

    // Create mock request with signature
    $request = Request::create(
        uri: '/webhook/jnt',
        method: 'POST',
        content: json_encode(['bizContent' => $bizContent]),
    );
    $request->headers->set('digest', $expectedSignature);
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt',
        'signing_secret' => $secret,
        'signature_header_name' => 'digest',
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
    $bizContent = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    expect($bizContent)->not->toBeFalse();

    // Create mock request with wrong signature
    $request = Request::create(
        uri: '/webhook/jnt',
        method: 'POST',
        content: json_encode(['bizContent' => $bizContent]),
    );
    $request->headers->set('digest', 'invalid-signature');
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt',
        'signing_secret' => $secret,
        'signature_header_name' => 'digest',
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
    $bizContent = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    expect($bizContent)->not->toBeFalse();

    // Create mock request without signature
    $request = Request::create(
        uri: '/webhook/jnt',
        method: 'POST',
        content: json_encode(['bizContent' => $bizContent]),
    );
    $request->headers->set('Content-Type', 'application/json');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt',
        'signing_secret' => $secret,
        'signature_header_name' => 'digest',
        'signature_validator' => JntSignatureValidator::class,
        'webhook_profile' => JntWebhookProfile::class,
        'webhook_model' => Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => AIArmada\Jnt\Webhooks\ProcessJntWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeFalse();
});
