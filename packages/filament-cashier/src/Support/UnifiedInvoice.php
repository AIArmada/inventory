<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

use Carbon\CarbonImmutable;

/**
 * Unified Invoice DTO - normalizes invoice data across gateways.
 */
final readonly class UnifiedInvoice
{
    public function __construct(
        public string $id,
        public string $gateway,
        public string $userId,
        public string $number,
        public int $amount,
        public string $currency,
        public InvoiceStatus $status,
        public CarbonImmutable $date,
        public ?CarbonImmutable $dueDate,
        public ?CarbonImmutable $paidAt,
        public ?string $pdfUrl,
        public object $original,
    ) {}

    /**
     * Create from a Stripe invoice.
     */
    public static function fromStripe(object $invoice, string $userId): self
    {
        $invoiceDate = $invoice->date();
        $dueDate = $invoice->dueDate();

        return new self(
            id: $invoice->id,
            gateway: 'stripe',
            userId: $userId,
            number: $invoice->number ?? $invoice->id,
            amount: (int) $invoice->rawTotal(),
            currency: mb_strtoupper($invoice->currency ?? 'USD'),
            status: self::normalizeStripeStatus($invoice),
            date: $invoiceDate instanceof CarbonImmutable ? $invoiceDate : CarbonImmutable::parse($invoiceDate),
            dueDate: $dueDate instanceof CarbonImmutable ? $dueDate : ($dueDate ? CarbonImmutable::parse($dueDate) : null),
            paidAt: $invoice->paid ? CarbonImmutable::createFromTimestamp($invoice->asStripeInvoice()->status_transitions?->paid_at ?? time()) : null,
            pdfUrl: $invoice->invoicePdf(),
            original: $invoice,
        );
    }

    /**
     * Create from a CHIP invoice/purchase.
     */
    public static function fromChip(object $invoice, string $userId): self
    {
        $createdAt = $invoice->created_at ?? now();

        return new self(
            id: (string) ($invoice->id ?? $invoice->getKey()),
            gateway: 'chip',
            userId: $userId,
            number: $invoice->reference ?? $invoice->id ?? '',
            amount: (int) ($invoice->amount ?? 0),
            currency: 'MYR',
            status: self::normalizeChipStatus($invoice),
            date: $createdAt instanceof CarbonImmutable ? $createdAt : CarbonImmutable::parse($createdAt),
            dueDate: null,
            paidAt: isset($invoice->paid_at) ? CarbonImmutable::parse($invoice->paid_at) : null,
            pdfUrl: $invoice->pdf_url ?? null,
            original: $invoice,
        );
    }

    /**
     * Get formatted amount for display.
     */
    public function formattedAmount(): string
    {
        $symbol = match ($this->currency) {
            'MYR' => 'RM',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $this->currency . ' ',
        };

        return $symbol . number_format($this->amount / 100, 2);
    }

    /**
     * Get the gateway configuration.
     *
     * @return array{label: string, icon: string, color: string, dashboard_url: string}
     */
    public function gatewayConfig(): array
    {
        return app(GatewayDetector::class)->getGatewayConfig($this->gateway);
    }

    /**
     * Get external dashboard URL for this invoice.
     */
    public function externalDashboardUrl(): string
    {
        $baseUrl = $this->gatewayConfig()['dashboard_url'];

        return match ($this->gateway) {
            'stripe' => "{$baseUrl}/invoices/{$this->id}",
            'chip' => "{$baseUrl}/purchases/{$this->id}",
            default => $baseUrl,
        };
    }

    private static function normalizeStripeStatus(object $invoice): InvoiceStatus
    {
        if ($invoice->paid) {
            return InvoiceStatus::Paid;
        }

        $status = $invoice->asStripeInvoice()->status ?? 'open';

        return match ($status) {
            'paid' => InvoiceStatus::Paid,
            'open' => InvoiceStatus::Open,
            'draft' => InvoiceStatus::Draft,
            'void' => InvoiceStatus::Void,
            'uncollectible' => InvoiceStatus::Uncollectible,
            default => InvoiceStatus::Open,
        };
    }

    private static function normalizeChipStatus(object $invoice): InvoiceStatus
    {
        $status = $invoice->status ?? 'pending';

        return match ($status) {
            'paid', 'success', 'completed' => InvoiceStatus::Paid,
            'pending', 'open' => InvoiceStatus::Open,
            'failed', 'error' => InvoiceStatus::Void,
            default => InvoiceStatus::Open,
        };
    }
}
