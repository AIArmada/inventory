<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Models\AffiliatePayout;
use DateTimeInterface;

interface PayoutProcessorInterface
{
    /**
     * Process a payout.
     */
    public function process(AffiliatePayout $payout): PayoutResult;

    /**
     * Get the current status of a payout from the processor.
     */
    public function getStatus(AffiliatePayout $payout): string;

    /**
     * Cancel a pending payout.
     */
    public function cancel(AffiliatePayout $payout): bool;

    /**
     * Get the estimated arrival time for a payout.
     */
    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface;

    /**
     * Get the processing fees for a given amount.
     */
    public function getFees(int $amountMinor, string $currency): int;

    /**
     * Validate payout method details.
     */
    public function validateDetails(array $details): array;

    /**
     * Get the processor identifier.
     */
    public function getIdentifier(): string;
}
