<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

/**
 * Document types supported by the docs package.
 */
enum DocType: string
{
    case Invoice = 'invoice';
    case Quotation = 'quotation';
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
    case ProformaInvoice = 'proforma_invoice';
    case Receipt = 'receipt';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Quotation => 'Quotation',
            self::CreditNote => 'Credit Note',
            self::DeliveryNote => 'Delivery Note',
            self::ProformaInvoice => 'Proforma Invoice',
            self::Receipt => 'Receipt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Invoice => 'primary',
            self::Quotation => 'info',
            self::CreditNote => 'danger',
            self::DeliveryNote => 'warning',
            self::ProformaInvoice => 'secondary',
            self::Receipt => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Invoice => 'heroicon-o-document-text',
            self::Quotation => 'heroicon-o-document-magnifying-glass',
            self::CreditNote => 'heroicon-o-document-minus',
            self::DeliveryNote => 'heroicon-o-truck',
            self::ProformaInvoice => 'heroicon-o-document-duplicate',
            self::Receipt => 'heroicon-o-receipt-percent',
        };
    }

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Invoice => 'INV',
            self::Quotation => 'QUO',
            self::CreditNote => 'CN',
            self::DeliveryNote => 'DN',
            self::ProformaInvoice => 'PI',
            self::Receipt => 'RCP',
        };
    }

    /**
     * Check if this document type requires payment tracking.
     */
    public function requiresPayment(): bool
    {
        return in_array($this, [self::Invoice, self::ProformaInvoice], true);
    }

    /**
     * Check if this document type can be converted to an invoice.
     */
    public function canConvertToInvoice(): bool
    {
        return in_array($this, [self::Quotation, self::ProformaInvoice], true);
    }

    /**
     * Get the document types this type can be converted from.
     *
     * @return array<self>
     */
    public function getConversionSources(): array
    {
        return match ($this) {
            self::Invoice => [self::Quotation, self::ProformaInvoice],
            self::Receipt => [self::Invoice],
            self::CreditNote => [self::Invoice],
            self::DeliveryNote => [self::Invoice],
            default => [],
        };
    }
}
