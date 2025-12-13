<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Contracts;

use AIArmada\CashierChip\Payment;
use AIArmada\CashierChip\PaymentMethod;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Data\ClientData;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface BillableContract
{
    public function chipId(): ?string;

    public function hasChipId(): bool;

    /**
     * @param  array<string, mixed>  $options
     */
    public function createOrGetChipCustomer(array $options = []): ClientData;

    public function chipName(): ?string;

    public function chipEmail(): ?string;

    public function chipPhone(): ?string;

    public function chipCountry(): ?string;

    /**
     * @return array<string, string>
     */
    public function chipAddress(): array;

    public function preferredCurrency(): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function charge(int $amount, ?string $recurringToken = null, array $options = []): Payment;

    /**
     * @param  array<string, mixed>  $options
     */
    public function chargeWithRecurringToken(int $amount, ?string $recurringToken = null, array $options = []): Payment;

    public function defaultPaymentMethod(): ?PaymentMethod;

    public function updateDefaultPaymentMethod(string $paymentMethodId): static;

    public function deletePaymentMethod(string $paymentMethodId): void;

    public function subscriptions(): HasMany;

    public function subscription(string $type = 'default'): ?Subscription;
}
