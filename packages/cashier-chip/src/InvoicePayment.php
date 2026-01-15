<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * Represents a payment applied to an invoice.
 *
 * In CHIP, this wraps a Purchase object since each purchase
 * represents a direct payment (unlike Stripe's separate InvoicePayment object).
 *
 * @implements Arrayable<string, mixed>
 */
class InvoicePayment implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Create a new InvoicePayment instance.
     */
    public function __construct(protected PurchaseData $purchase)
    {
        //
    }

    /**
     * Dynamically get values from the Purchase object.
     */
    public function __get(string $key): mixed
    {
        return $this->purchase->{$key};
    }

    /**
     * Get the payment ID.
     */
    public function id(): string
    {
        return $this->purchase->id;
    }

    /**
     * Get the allocated amount as a formatted string.
     */
    public function amount(): string
    {
        return Cashier::formatAmount($this->rawAmount(), $this->currency());
    }

    /**
     * Get the raw allocated amount in the smallest currency unit.
     */
    public function rawAmount(): int
    {
        // CHIP stores amounts in smallest unit (cents/sen)
        return $this->purchase->payment?->getAmountInCents() ?? 0;
    }

    /**
     * Get the currency of the payment.
     */
    public function currency(): string
    {
        return $this->purchase->payment?->getCurrency() ?? config('cashier-chip.currency', 'MYR');
    }

    /**
     * Get the payment status.
     */
    public function status(): string
    {
        return $this->purchase->status ?? 'unknown';
    }

    /**
     * Determine if the payment is completed.
     */
    public function isCompleted(): bool
    {
        return in_array($this->purchase->status, ['paid', 'success'], true);
    }

    /**
     * Determine if the payment is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->purchase->status, ['pending', 'created'], true);
    }

    /**
     * Determine if the payment failed.
     */
    public function isFailed(): bool
    {
        return in_array($this->purchase->status, ['failed', 'error'], true);
    }

    /**
     * Get the date when the payment was made.
     */
    public function date(): ?\Carbon\CarbonImmutable
    {
        return $this->purchase->payment?->getPaidAt();
    }

    /**
     * Get the CHIP Purchase instance.
     */
    public function asChipPurchase(): PurchaseData
    {
        return $this->purchase;
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'amount' => $this->rawAmount(),
            'currency' => $this->currency(),
            'status' => $this->status(),
            'is_completed' => $this->isCompleted(),
            'date' => $this->date()?->toIso8601String(),
        ];
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options) ?: '';
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
