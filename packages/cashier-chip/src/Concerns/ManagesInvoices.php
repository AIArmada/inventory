<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Invoice;
use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

trait ManagesInvoices // @phpstan-ignore trait.unused
{
    /**
     * Stored tab items for later invoicing (in-memory).
     *
     * @var array<int, array{name: string, price: int, quantity: int}>
     */
    public array $tabs = [];

    /**
     * Get all of the invoices for the Billable model.
     *
     * @return Collection<int, Invoice>
     */
    public function invoices(): Collection
    {
        if (! $this->hasChipId()) {
            return collect();
        }

        // Get all subscriptions and their invoices
        $invoices = collect();

        foreach ($this->subscriptions as $subscription) {
            $subscriptionInvoices = $subscription->invoices();
            $invoices = $invoices->merge($subscriptionInvoices);
        }

        return $invoices->sortByDesc('created_at');
    }

    /**
     * Get all of the invoices for the Billable model, including pending.
     *
     * @return Collection<int, Invoice>
     */
    public function invoicesIncludingPending(): Collection
    {
        return $this->invoices();
    }

    /**
     * Find an invoice by ID.
     */
    public function findInvoice(string $invoiceId): ?Invoice
    {
        try {
            $purchaseData = Cashier::chip()->getPurchase($invoiceId);

            if (is_array($purchaseData)) {
                $purchase = PurchaseData::from($purchaseData);
            } else {
                $purchase = $purchaseData;
            }

            return new Invoice($this, $purchase);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the upcoming invoice for the customer.
     */
    public function upcomingInvoice(): ?Invoice
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return null;
        }

        return $subscription->upcomingInvoice();
    }

    /**
     * Add a single invoice item for the customer.
     *
     * @param  array<string, mixed>  $options
     */
    public function tab(string $name, int $amount, array $options = []): self
    {
        $quantity = (int) ($options['quantity'] ?? 1);

        $this->tabs[] = [
            'name' => $name,
            'price' => $amount,
            'quantity' => max(1, $quantity),
        ];

        return $this;
    }

    /**
     * Invoice the customer for the stored tab items.
     *
     * @param  array<string, mixed>  $options
     */
    public function invoice(array $options = []): Invoice
    {
        $tabs = $this->tabs;

        if (empty($tabs)) {
            throw new RuntimeException('No items to invoice.');
        }

        $builder = Cashier::chip()->purchase()
            ->currency($this->preferredCurrency());

        foreach ($tabs as $tab) {
            $builder->addProduct($tab['name'], $tab['price'], $tab['quantity']);
        }

        if ($this->hasChipId()) {
            $builder->clientId($this->chip_id);
        } else {
            $builder->customer(
                email: $this->chipEmail() ?? '',
                fullName: $this->chipName()
            );
        }

        if (isset($options['success_url'])) {
            $builder->successUrl($options['success_url']);
        }

        if (isset($options['failure_url'])) {
            $builder->failureUrl($options['failure_url']);
        }

        $purchase = $builder->create();

        // Clear tabs
        $this->tabs = [];

        return new Invoice($this, $purchase);
    }

    /**
     * Create an invoice for the given price.
     *
     * @param  array<string, mixed>  $options
     */
    public function invoicePrice(string $price, int $quantity = 1, array $options = []): Invoice
    {
        return $this->tab($price, $options['price'] ?? 0, ['quantity' => $quantity])
            ->invoice($options);
    }

    /**
     * Create an invoice for the given amount.
     *
     * @param  array<string, mixed>  $options
     */
    public function invoiceFor(string $name, int $amount, array $options = []): Invoice
    {
        return $this->tab($name, $amount, $options)->invoice($options);
    }
}
