<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator;

/**
 * Validates CHIP webhook signatures.
 *
 * CHIP uses HMAC-SHA256 for webhook signature verification.
 */
class ChipSignatureValidator extends CommerceSignatureValidator
{
    /**
     * Get the header containing the signature.
     */
    protected function getSignatureHeader(): string
    {
        return 'X-Signature';
    }

    /**
     * Get the hash algorithm.
     */
    protected function getHashAlgorithm(): string
    {
        return 'sha256';
    }
}
