<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Validates incoming webhook signatures.
 */
class WebhookValidator
{
    /**
     * Validate the webhook request signature.
     */
    public function validate(Request $request): bool
    {
        $signature = $request->header($this->getSignatureHeader());

        if (empty($signature)) {
            $this->logFailure('Missing signature header');

            return false;
        }

        $secret = config('chip.webhook_secret');

        if (empty($secret)) {
            $this->logFailure('Webhook secret not configured');

            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac($this->getHashAlgorithm(), $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            $this->logFailure('Invalid signature');

            return false;
        }

        return true;
    }

    /**
     * Get the header containing the signature.
     */
    protected function getSignatureHeader(): string
    {
        return config('chip.webhook_signature_header', 'X-Signature');
    }

    /**
     * Get the hash algorithm.
     */
    protected function getHashAlgorithm(): string
    {
        return 'sha256';
    }

    /**
     * Log validation failure.
     */
    protected function logFailure(string $reason): void
    {
        Log::channel(config('chip.logging.channel', 'stack'))
            ->warning('Webhook signature validation failed', [
                'reason' => $reason,
            ]);
    }
}
