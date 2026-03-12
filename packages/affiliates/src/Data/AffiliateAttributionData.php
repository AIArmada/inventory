<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Models\AffiliateAttribution;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Lightweight DTO describing an attribution row.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class AffiliateAttributionData extends Data
{
    public readonly string $id;

    public readonly string $affiliateId;

    public readonly string $affiliateCode;

    public readonly ?string $subjectType;

    public readonly ?string $subjectIdentifier;

    public readonly string $subjectInstance;

    public readonly ?string $subjectTitleSnapshot;

    public readonly ?string $cartIdentifier;

    public readonly string $cartInstance;

    public readonly ?string $cookieValue;

    public readonly ?string $voucherCode;

    public readonly ?string $source;

    public readonly ?string $medium;

    public readonly ?string $campaign;

    public readonly ?CarbonInterface $expiresAt;

    public readonly ?string $ownerType;

    public readonly string | int | null $ownerId;

    /**
     * @var array<string, mixed>|null
     */
    public readonly ?array $metadata;

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        string $id,
        string $affiliateId,
        string $affiliateCode,
        ?string $subjectIdentifier = null,
        ?string $subjectInstance = null,
        ?string $cartIdentifier = null,
        string $cartInstance = 'default',
        ?string $cookieValue = null,
        ?string $voucherCode = null,
        ?string $source = null,
        ?string $medium = null,
        ?string $campaign = null,
        ?CarbonInterface $expiresAt = null,
        ?string $ownerType = null,
        string | int | null $ownerId = null,
        ?array $metadata = null,
        ?string $subjectType = null,
        ?string $subjectTitleSnapshot = null,
    ) {
        $resolvedSubjectIdentifier = $subjectIdentifier ?? $cartIdentifier;
        $resolvedSubjectInstance = $subjectInstance ?? $cartInstance;

        $this->id = $id;
        $this->affiliateId = $affiliateId;
        $this->affiliateCode = $affiliateCode;
        $this->subjectType = $subjectType;
        $this->subjectIdentifier = $resolvedSubjectIdentifier;
        $this->subjectInstance = $resolvedSubjectInstance;
        $this->subjectTitleSnapshot = $subjectTitleSnapshot;
        $this->cartIdentifier = $cartIdentifier ?? $resolvedSubjectIdentifier;
        $this->cartInstance = $cartInstance !== 'default' || $subjectInstance === null ? $cartInstance : $resolvedSubjectInstance;
        $this->cookieValue = $cookieValue;
        $this->voucherCode = $voucherCode;
        $this->source = $source;
        $this->medium = $medium;
        $this->campaign = $campaign;
        $this->expiresAt = $expiresAt;
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->metadata = $metadata;
    }

    public static function fromModel(AffiliateAttribution $attribution): self
    {
        return new self(
            id: (string) $attribution->getKey(),
            affiliateId: (string) $attribution->affiliate_id,
            affiliateCode: (string) $attribution->affiliate_code,
            subjectType: $attribution->subject_type,
            subjectIdentifier: $attribution->subject_identifier,
            subjectInstance: $attribution->subject_instance,
            subjectTitleSnapshot: $attribution->subject_title_snapshot,
            cartIdentifier: $attribution->cart_identifier,
            cartInstance: (string) $attribution->cart_instance,
            cookieValue: $attribution->cookie_value,
            voucherCode: $attribution->voucher_code,
            source: $attribution->source,
            medium: $attribution->medium,
            campaign: $attribution->campaign,
            expiresAt: $attribution->expires_at,
            ownerType: $attribution->owner_type,
            ownerId: $attribution->owner_id,
            metadata: $attribution->metadata,
        );
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt->isPast();
    }

    public function hasUtmParameters(): bool
    {
        return $this->source !== null || $this->medium !== null || $this->campaign !== null;
    }

    public function getUtmString(): ?string
    {
        if (! $this->hasUtmParameters()) {
            return null;
        }

        $parts = array_filter([
            $this->source ? "utm_source={$this->source}" : null,
            $this->medium ? "utm_medium={$this->medium}" : null,
            $this->campaign ? "utm_campaign={$this->campaign}" : null,
        ]);

        return implode('&', $parts);
    }
}
