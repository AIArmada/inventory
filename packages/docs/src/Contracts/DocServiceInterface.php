<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Models\DocVersion;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for document management services.
 */
interface DocServiceInterface
{
    /**
     * Generate a document number for the given type.
     */
    public function generateNumber(string $docType = 'invoice'): string;

    /**
     * Create a new document from DocData DTO.
     */
    public function create(DocData $data): Doc;

    /**
     * Create a new document from array data with DocType enum.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromType(DocType $type, array $data, ?Model $owner = null): Doc;

    /**
     * Update a document and create a version snapshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Doc $doc, array $data): Doc;

    /**
     * Convert a document to another type.
     */
    public function convert(Doc $source, DocType $targetType, ?Model $owner = null): Doc;

    /**
     * Record a payment against a document.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function recordPayment(Doc $doc, array $paymentData): DocPayment;

    /**
     * Clone a document.
     */
    public function clone(Doc $source, ?Model $owner = null): Doc;

    /**
     * Create a version snapshot.
     */
    public function createVersion(Doc $doc, ?string $summary = null): DocVersion;

    /**
     * Generate a PDF for a document.
     *
     * @return string The relative path to the stored PDF (or raw PDF content if $save is false)
     */
    public function generatePdf(Doc $doc, bool $save = true): string;

    /**
     * Download or retrieve PDF path for a document.
     *
     * @return string The relative path to the PDF
     */
    public function downloadPdf(Doc $doc): string;

    /**
     * Mark a document as sent (typically after emailing).
     */
    public function markAsSent(Doc $doc, ?string $notes = null): void;

    /**
     * Update a document's status with audit trail.
     */
    public function updateStatus(Doc $doc, DocStatus $status, ?string $notes = null): void;

    /**
     * Calculate document totals from items.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, tax_amount: float, total: float}
     */
    public function calculateTotals(array $items, float $discountAmount = 0): array;

    /**
     * Resolve the storage disk for a document type.
     */
    public function resolveStorageDiskForDocType(string $docType): string;
}
