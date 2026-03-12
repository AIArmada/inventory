<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PendingConversion;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * DTO representing a conversion + commission record.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class AffiliateConversionData extends Data
{
    public readonly ConversionStatus $status;

    public readonly string $id;

    public readonly string $affiliateId;

    public readonly string $affiliateCode;

    public readonly ?string $subjectType;

    public readonly ?string $subjectIdentifier;

    public readonly ?string $subjectInstance;

    public readonly ?string $subjectTitleSnapshot;

    public readonly ?string $cartIdentifier;

    public readonly ?string $cartInstance;

    public readonly ?string $voucherCode;

    public readonly ?string $externalReference;

    public readonly ?string $orderReference;

    public readonly ?string $conversionType;

    public readonly int $subtotalMinor;

    public readonly int $valueMinor;

    public readonly int $totalMinor;

    public readonly int $commissionMinor;

    public readonly string $commissionCurrency;

    public readonly ?CarbonInterface $occurredAt;

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
        ?string $cartInstance = null,
        ?string $voucherCode = null,
        ?string $externalReference = null,
        ?string $orderReference = null,
        ?string $conversionType = null,
        int $subtotalMinor = 0,
        int $valueMinor = 0,
        int $totalMinor = 0,
        int $commissionMinor = 0,
        string $commissionCurrency = 'MYR',
        ?ConversionStatus $status = null,
        ?CarbonInterface $occurredAt = null,
        ?string $ownerType = null,
        string | int | null $ownerId = null,
        ?array $metadata = null,
        ?string $subjectType = null,
        ?string $subjectTitleSnapshot = null,
    ) {
        $resolvedSubjectIdentifier = $subjectIdentifier ?? $cartIdentifier;
        $resolvedSubjectInstance = $subjectInstance ?? $cartInstance;
        $resolvedExternalReference = $externalReference ?? $orderReference;
        $resolvedValueMinor = $valueMinor !== 0 || $totalMinor === 0 ? $valueMinor : $totalMinor;

        $this->id = $id;
        $this->affiliateId = $affiliateId;
        $this->affiliateCode = $affiliateCode;
        $this->subjectType = $subjectType;
        $this->subjectIdentifier = $resolvedSubjectIdentifier;
        $this->subjectInstance = $resolvedSubjectInstance;
        $this->subjectTitleSnapshot = $subjectTitleSnapshot;
        $this->cartIdentifier = $cartIdentifier ?? $resolvedSubjectIdentifier;
        $this->cartInstance = $cartInstance ?? $resolvedSubjectInstance;
        $this->voucherCode = $voucherCode;
        $this->externalReference = $resolvedExternalReference;
        $this->orderReference = $orderReference ?? $resolvedExternalReference;
        $this->conversionType = $conversionType;
        $this->subtotalMinor = $subtotalMinor;
        $this->valueMinor = $resolvedValueMinor;
        $this->totalMinor = $totalMinor !== 0 || $resolvedValueMinor === 0 ? $totalMinor : $resolvedValueMinor;
        $this->commissionMinor = $commissionMinor;
        $this->commissionCurrency = $commissionCurrency;
        $this->status = $status ?? ConversionStatus::fromString(PendingConversion::class);
        $this->occurredAt = $occurredAt;
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->metadata = $metadata;
    }

    public static function fromModel(AffiliateConversion $conversion): self
    {
        $status = $conversion->status;

        if (! $status instanceof ConversionStatus) {
            $status = ConversionStatus::fromString((string) $status);
        }

        return new self(
            id: (string) $conversion->getKey(),
            affiliateId: (string) $conversion->affiliate_id,
            affiliateCode: (string) $conversion->affiliate_code,
            subjectType: $conversion->subject_type,
            subjectIdentifier: $conversion->subject_identifier,
            subjectInstance: $conversion->subject_instance,
            subjectTitleSnapshot: $conversion->subject_title_snapshot,
            cartIdentifier: $conversion->cart_identifier,
            cartInstance: $conversion->cart_instance,
            voucherCode: $conversion->voucher_code,
            externalReference: $conversion->external_reference,
            orderReference: $conversion->order_reference,
            subtotalMinor: (int) $conversion->subtotal_minor,
            valueMinor: (int) $conversion->value_minor,
            totalMinor: (int) $conversion->total_minor,
            commissionMinor: (int) $conversion->commission_minor,
            commissionCurrency: (string) $conversion->commission_currency,
            conversionType: $conversion->conversion_type,
            status: $status,
            occurredAt: $conversion->occurred_at,
            ownerType: $conversion->owner_type,
            ownerId: $conversion->owner_id,
            metadata: $conversion->metadata,
        );
    }

    public function isPending(): bool
    {
        return $this->status->equals(PendingConversion::class);
    }

    public function isApproved(): bool
    {
        return $this->status->equals(ApprovedConversion::class);
    }

    public function getFormattedCommission(): string
    {
        return number_format($this->commissionMinor / 100, 2) . ' ' . $this->commissionCurrency;
    }

    public function getFormattedTotal(): string
    {
        return number_format($this->totalMinor / 100, 2) . ' ' . $this->commissionCurrency;
    }
}
