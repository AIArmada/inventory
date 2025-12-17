<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Exceptions\WebhookVerificationException;
use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;

/**
 * Backwards-compatible webhook validator.
 *
 * Signature verification is delegated to WebhookService.
 */
final class WebhookValidator
{
    public function validate(Request $request): bool
    {
        try {
            return app(WebhookService::class)->verifySignature($request);
        } catch (WebhookVerificationException) {
            return false;
        }
    }
}
