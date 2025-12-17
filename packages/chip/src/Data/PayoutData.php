<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Akaunting\Money\Money;
use Carbon\Carbon;

/**
 * CHIP Payout data object.
 *
 * Represents a payout/disbursement from CHIP.
 */
class PayoutData extends ChipData
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly int $created_on,
        public readonly int $updated_on,
        public readonly string $status,
        public readonly Money $amount,
        public readonly string $currency,
        public readonly ?string $reference,
        public readonly ?string $description,
        public readonly ?string $recipient_bank_account,
        public readonly ?string $recipient_bank_code,
        public readonly ?string $recipient_name,
        public readonly ?string $company_id,
        public readonly bool $is_test,
        /** @var array<string, mixed> */
        public readonly array $metadata,
        /** @var array<string, mixed> */
        public readonly array $error,
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);
        $currency = $data['currency'] ?? 'MYR';
        $amount = $data['amount'] ?? 0;

        return new self(
            id: (string) ($data['id'] ?? ''),
            type: $data['type'] ?? 'payout',
            created_on: $data['created_on'] ?? time(),
            updated_on: $data['updated_on'] ?? time(),
            status: $data['status'] ?? 'pending',
            amount: Money::{$currency}($amount),
            currency: $currency,
            reference: $data['reference'] ?? null,
            description: $data['description'] ?? null,
            recipient_bank_account: $data['recipient_bank_account'] ?? null,
            recipient_bank_code: $data['recipient_bank_code'] ?? null,
            recipient_name: $data['recipient_name'] ?? null,
            company_id: $data['company_id'] ?? null,
            is_test: $data['is_test'] ?? true,
            metadata: $data['metadata'] ?? [],
            error: $data['error'] ?? [],
        );
    }

    public function getCreatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->created_on);
    }

    public function getUpdatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->updated_on);
    }

    public function getAmountInCents(): int
    {
        return (int) $this->amount->getAmount();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'error' || $this->status === 'failed';
    }

    public function hasError(): bool
    {
        return ! empty($this->error);
    }

    public function getErrorMessage(): ?string
    {
        return $this->error['message'] ?? null;
    }

    public function getErrorCode(): ?string
    {
        return $this->error['code'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'created_on' => $this->created_on,
            'updated_on' => $this->updated_on,
            'status' => $this->status,
            'amount' => $this->getAmountInCents(),
            'currency' => $this->currency,
            'reference' => $this->reference,
            'description' => $this->description,
            'recipient_bank_account' => $this->recipient_bank_account,
            'recipient_bank_code' => $this->recipient_bank_code,
            'recipient_name' => $this->recipient_name,
            'company_id' => $this->company_id,
            'is_test' => $this->is_test,
            'metadata' => $this->metadata,
            'error' => $this->error,
        ];
    }
}
