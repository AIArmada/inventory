<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Payouts;

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Models\AffiliatePayout;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Manual payout processor for bank transfers, checks, etc.
 */
final class ManualPayoutProcessor implements PayoutProcessorInterface
{
    public function process(AffiliatePayout $payout): PayoutResult
    {
        return PayoutResult::pending(
            externalReference: 'MANUAL-'.Str::upper(Str::random(12)),
            metadata: [
                'type' => 'manual',
                'requires_admin_action' => true,
            ]
        );
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        return $payout->status;
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
    {
        return now()->addDays(5);
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'manual';
    }
}
