<?php

declare(strict_types=1);

use AIArmada\Jnt\Webhooks\JntSpatieSignatureValidator;
use AIArmada\Jnt\Webhooks\JntWebhookProfile;
use AIArmada\Jnt\Webhooks\JntWebhookResponse;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\Models\WebhookCall;

it('validates a correct signature', function (): void {
    config()->set('jnt.webhooks.verify_signature', true);
    config()->set('jnt.private_key', 'jnt-test-secret');

    $validator = new JntSpatieSignatureValidator;
    $secret = (string) config('jnt.private_key');
    $bizContent = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    expect($bizContent)->not->toBeFalse();

    // Compute expected signature
    $expectedSignature = base64_encode(md5($bizContent . $secret, true));

    // Create mock request with signature
    $request = Request::create('/webhook/jnt', 'POST', ['bizContent' => $bizContent]);
    $request->headers->set('digest', $expectedSignature);

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt.webhooks.status',
        'signing_secret' => '',
        'signature_header_name' => 'digest',
        'signature_validator' => JntSpatieSignatureValidator::class,
        'webhook_profile' => JntWebhookProfile::class,
        'webhook_response' => JntWebhookResponse::class,
        'webhook_model' => WebhookCall::class,
        'store_headers' => ['digest'],
        'process_webhook_job' => AIArmada\Jnt\Webhooks\ProcessJntWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeTrue();
});

it('rejects an incorrect signature', function (): void {
    config()->set('jnt.webhooks.verify_signature', true);
    config()->set('jnt.private_key', 'jnt-test-secret');

    $validator = new JntSpatieSignatureValidator;
    $bizContent = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    expect($bizContent)->not->toBeFalse();

    // Create mock request with wrong signature
    $request = Request::create('/webhook/jnt', 'POST', ['bizContent' => $bizContent]);
    $request->headers->set('digest', 'invalid-signature');

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt.webhooks.status',
        'signing_secret' => '',
        'signature_header_name' => 'digest',
        'signature_validator' => JntSpatieSignatureValidator::class,
        'webhook_profile' => JntWebhookProfile::class,
        'webhook_response' => JntWebhookResponse::class,
        'webhook_model' => WebhookCall::class,
        'store_headers' => ['digest'],
        'process_webhook_job' => AIArmada\Jnt\Webhooks\ProcessJntWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeFalse();
});

it('rejects request without signature header', function (): void {
    config()->set('jnt.webhooks.verify_signature', true);
    config()->set('jnt.private_key', 'jnt-test-secret');

    $validator = new JntSpatieSignatureValidator;
    $bizContent = json_encode(['event' => 'shipment.delivered', 'awb' => 'JNT123456789']);

    expect($bizContent)->not->toBeFalse();

    // Create mock request without signature
    $request = Request::create('/webhook/jnt', 'POST', ['bizContent' => $bizContent]);

    // Create mock webhook config
    $config = new WebhookConfig([
        'name' => 'jnt.webhooks.status',
        'signing_secret' => '',
        'signature_header_name' => 'digest',
        'signature_validator' => JntSpatieSignatureValidator::class,
        'webhook_profile' => JntWebhookProfile::class,
        'webhook_response' => JntWebhookResponse::class,
        'webhook_model' => WebhookCall::class,
        'store_headers' => ['digest'],
        'process_webhook_job' => AIArmada\Jnt\Webhooks\ProcessJntWebhook::class,
    ]);

    expect($validator->isValid($request, $config))->toBeFalse();
});
