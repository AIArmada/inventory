<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator;

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
        return 'X-JNT-Signature';
    }

    /**
     * Get the hash algorithm.
     */
    protected function getHashAlgorithm(): string
    {
        return 'sha256';
    }
}
