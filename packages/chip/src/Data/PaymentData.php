<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Akaunting\Money\Money;
use Carbon\Carbon;

final class PaymentData extends ChipData
{
    public function __construct(
        public readonly bool $is_outgoing,
        public readonly string $payment_type,
        public readonly Money $amount,
        public readonly Money $net_amount,
        public readonly Money $fee_amount,
        public readonly Money $pending_amount,
        public readonly ?int $pending_unfreeze_on,
        public readonly ?string $description,
        public readonly ?int $paid_on,
        public readonly ?int $remote_paid_on,
    ) {}

    /**
     * Create a Payment from array data (typically from CHIP API response).
     * Amounts in the array are expected to be in cents (minor units).
     *
     * @param  array<string, mixed>|self  ...$payloads
     */
    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);
        $currency = $data['currency'] ?? 'MYR';

        return new self(
            is_outgoing: $data['is_outgoing'] ?? false,
            payment_type: $data['payment_type'] ?? 'purchase',
            amount: Money::{$currency}((int) ($data['amount'] ?? 0)),
            net_amount: Money::{$currency}((int) ($data['net_amount'] ?? 0)),
            fee_amount: Money::{$currency}((int) ($data['fee_amount'] ?? 0)),
            pending_amount: Money::{$currency}((int) ($data['pending_amount'] ?? 0)),
            pending_unfreeze_on: isset($data['pending_unfreeze_on']) ? (int) $data['pending_unfreeze_on'] : null,
            description: $data['description'] ?? null,
            paid_on: isset($data['paid_on']) ? (int) $data['paid_on'] : null,
            remote_paid_on: isset($data['remote_paid_on']) ? (int) $data['remote_paid_on'] : null,
        );
    }

    /**
     * Get the currency code for this payment.
     */
    public function getCurrency(): string
    {
        return $this->amount->getCurrency()->getCurrency();
    }

    /**
     * Get amount in cents for API communication.
     */
    public function getAmountInCents(): int
    {
        return (int) $this->amount->getAmount();
    }

    /**
     * Get net amount in cents for API communication.
     */
    public function getNetAmountInCents(): int
    {
        return (int) $this->net_amount->getAmount();
    }

    /**
     * Get fee amount in cents for API communication.
     */
    public function getFeeAmountInCents(): int
    {
        return (int) $this->fee_amount->getAmount();
    }

    /**
     * Get pending amount in cents for API communication.
     */
    public function getPendingAmountInCents(): int
    {
        return (int) $this->pending_amount->getAmount();
    }

    public function getPaidAt(): ?Carbon
    {
        return $this->paid_on ? Carbon::createFromTimestamp($this->paid_on) : null;
    }

    public function getRemotePaidAt(): ?Carbon
    {
        return $this->remote_paid_on ? Carbon::createFromTimestamp($this->remote_paid_on) : null;
    }

    public function getPendingUnfreezeAt(): ?Carbon
    {
        return $this->pending_unfreeze_on ? Carbon::createFromTimestamp($this->pending_unfreeze_on) : null;
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    /**
     * Convert to array for CHIP API (amounts in cents).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_outgoing' => $this->is_outgoing,
            'payment_type' => $this->payment_type,
            'amount' => $this->getAmountInCents(),
            'currency' => $this->getCurrency(),
            'net_amount' => $this->getNetAmountInCents(),
            'fee_amount' => $this->getFeeAmountInCents(),
            'pending_amount' => $this->getPendingAmountInCents(),
            'pending_unfreeze_on' => $this->pending_unfreeze_on,
            'description' => $this->description,
            'paid_on' => $this->paid_on,
            'remote_paid_on' => $this->remote_paid_on,
        ];
    }
}
