<?php

declare(strict_types=1);

namespace AIArmada\Docs\DataObjects;

use AIArmada\Docs\Enums\DocStatus;
use DateTimeInterface;

final class DocData
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $customerData
     * @param  array<string, mixed>|null  $companyData
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $pdfOptions
     */
    public function __construct(
        public readonly ?string $docNumber = null,
        public readonly ?string $docType = null,
        public readonly ?string $docTemplateId = null,
        public readonly ?string $templateSlug = null,
        public readonly ?string $docableType = null,
        public readonly ?string $docableId = null,
        public readonly ?DocStatus $status = null,
        public readonly ?DateTimeInterface $issueDate = null,
        public readonly ?DateTimeInterface $dueDate = null,
        public readonly array $items = [],
        public readonly ?float $subtotal = null,
        public readonly ?float $total = null,
        public readonly ?float $taxRate = null,
        public readonly ?float $taxAmount = null,
        public readonly ?float $discountAmount = null,
        public readonly ?string $currency = null,
        public readonly ?string $notes = null,
        public readonly ?string $terms = null,
        public readonly ?array $customerData = null,
        public readonly ?array $companyData = null,
        public readonly ?array $metadata = null,
        public readonly ?array $pdfOptions = null,
        public readonly ?bool $generatePdf = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            docNumber: $data['doc_number'] ?? null,
            docType: $data['doc_type'] ?? 'invoice',
            docTemplateId: $data['doc_template_id'] ?? null,
            templateSlug: $data['template_slug'] ?? null,
            docableType: $data['docable_type'] ?? null,
            docableId: $data['docable_id'] ?? null,
            status: isset($data['status']) ? (is_string($data['status']) ? DocStatus::from($data['status']) : $data['status']) : null,
            issueDate: $data['issue_date'] ?? null,
            dueDate: $data['due_date'] ?? null,
            items: $data['items'] ?? [],
            subtotal: $data['subtotal'] ?? null,
            total: $data['total'] ?? null,
            taxRate: $data['tax_rate'] ?? null,
            taxAmount: $data['tax_amount'] ?? null,
            discountAmount: $data['discount_amount'] ?? null,
            currency: $data['currency'] ?? null,
            notes: $data['notes'] ?? null,
            terms: $data['terms'] ?? null,
            customerData: $data['customer_data'] ?? null,
            companyData: $data['company_data'] ?? null,
            metadata: $data['metadata'] ?? null,
            pdfOptions: $data['pdf_options'] ?? $data['pdf'] ?? null,
            generatePdf: $data['generate_pdf'] ?? false,
        );
    }
}
