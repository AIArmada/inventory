<?php

declare(strict_types=1);

use AIArmada\Jnt\Webhooks\JntWebhookProfile;
use AIArmada\Jnt\Exceptions\JntValidationException;
use Illuminate\Http\Request;

describe('JntWebhookProfile', function (): void {
    it('should not process when bizContent is missing', function (): void {
        $request = Request::create('/webhook', 'POST', []);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('should not process when bizContent is empty', function (): void {
        $request = Request::create('/webhook', 'POST', ['bizContent' => '']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('should process when bizContent is valid', function (): void {
        $request = Request::create('/webhook', 'POST', [
            'bizContent' => json_encode([
                'billCode' => 'JNTMY12345678',
                'details' => [],
            ]),
        ]);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('throws when bizContent is invalid JSON', function (): void {
        $request = Request::create('/webhook', 'POST', [
            'bizContent' => 'not-json',
        ]);

        $profile = new JntWebhookProfile;

        $profile->shouldProcess($request);
    })->throws(JntValidationException::class);

    it('throws when billCode is missing', function (): void {
        $request = Request::create('/webhook', 'POST', [
            'bizContent' => json_encode([
                'details' => [],
            ]),
        ]);

        $profile = new JntWebhookProfile;

        $profile->shouldProcess($request);
    })->throws(JntValidationException::class);

    it('throws when details is not an array', function (): void {
        $request = Request::create('/webhook', 'POST', [
            'bizContent' => json_encode([
                'billCode' => 'JNTMY12345678',
                'details' => 'not-array',
            ]),
        ]);

        $profile = new JntWebhookProfile;

        $profile->shouldProcess($request);
    })->throws(JntValidationException::class);
});
