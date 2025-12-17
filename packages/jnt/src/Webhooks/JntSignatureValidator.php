<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator;
use Illuminate\Http\Request;

/**
 * Validates J&T webhook signatures.
 *
 * J&T uses their own signature format with the API key.
 */
class JntSignatureValidator extends CommerceSignatureValidator
{
    /**
     * Get the header containing the signature.
     */
    protected function getSignatureHeader(): string
    {
        return 'digest';
    }

    protected function getPayloadForSigning(Request $request): string
    {
        return (string) $request->input('bizContent', '');
    }

    protected function computeSignature(string $payload, string $secret): string
    {
        return base64_encode(md5($payload . $secret, true));
    }
}
