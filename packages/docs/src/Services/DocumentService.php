<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Models\DocVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Core document management service.
 */
final class DocumentService
{
    public function __construct(
        private readonly SequenceManager $sequenceManager,
    ) {}

    /**
     * Create a new document.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(DocType $type, array $data, ?Model $owner = null): Doc
    {
        return DB::transaction(function () use ($type, $data, $owner): Doc {
            // Generate document number
            $docNumber = $this->sequenceManager->generate($type, $owner);

            $docData = array_merge($data, [
                'doc_number' => $docNumber,
                'doc_type' => $type->value,
                'status' => DocStatus::DRAFT,
                'issue_date' => $data['issue_date'] ?? now(),
            ]);

            if ($owner) {
                $docData['owner_type'] = $owner->getMorphClass();
                $docData['owner_id'] = $owner->getKey();
            }

            // Calculate totals if items provided
            if (isset($data['items'])) {
                $totals = $this->calculateTotals($data['items'], $data['discount_amount'] ?? 0);
                $docData = array_merge($docData, $totals);
            }

            $doc = Doc::create($docData);

            // Create initial version
            $this->createVersion($doc, 'Initial creation');

            return $doc;
        });
    }

    /**
     * Update a document and create a version snapshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Doc $doc, array $data): Doc
    {
        return DB::transaction(function () use ($doc, $data): Doc {
            // Calculate totals if items changed
            if (isset($data['items'])) {
                $totals = $this->calculateTotals(
                    $data['items'],
                    $data['discount_amount'] ?? $doc->discount_amount
                );
                $data = array_merge($data, $totals);
            }

            $doc->update($data);

            // Create version snapshot
            $this->createVersion($doc, 'Document updated');

            return $doc->fresh() ?? $doc;
        });
    }

    /**
     * Convert a document to another type.
     */
    public function convert(Doc $source, DocType $targetType, ?Model $owner = null): Doc
    {
        $sourceType = DocType::tryFrom($source->doc_type);

        // Validate conversion is allowed
        $allowedSources = $targetType->getConversionSources();
        if ($sourceType && ! in_array($sourceType, $allowedSources, true)) {
            throw new InvalidArgumentException(
                "Cannot convert {$sourceType->label()} to {$targetType->label()}"
            );
        }

        // Create new document from source
        return $this->create($targetType, [
            'docable_type' => $source->docable_type,
            'docable_id' => $source->docable_id,
            'doc_template_id' => $source->doc_template_id,
            'due_date' => $source->due_date,
            'currency' => $source->currency,
            'notes' => $source->notes,
            'terms' => $source->terms,
            'customer_data' => $source->customer_data,
            'company_data' => $source->company_data,
            'items' => $source->items,
            'metadata' => array_merge($source->metadata ?? [], [
                'converted_from' => [
                    'doc_id' => $source->id,
                    'doc_number' => $source->doc_number,
                    'doc_type' => $source->doc_type,
                ],
            ]),
        ], $owner);
    }

    /**
     * Record a payment against a document.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function recordPayment(Doc $doc, array $paymentData): DocPayment
    {
        return DB::transaction(function () use ($doc, $paymentData): DocPayment {
            $payment = $doc->payments()->create(array_merge($paymentData, [
                'paid_at' => $paymentData['paid_at'] ?? now(),
                'currency' => $paymentData['currency'] ?? $doc->currency,
            ]));

            // Update document status based on payments
            $totalPaid = $doc->payments()->sum('amount');
            $docTotal = (float) $doc->total;

            if ($totalPaid >= $docTotal) {
                $doc->markAsPaid("Payment recorded: {$payment->amount}");
            } elseif ($totalPaid > 0) {
                $doc->update(['status' => DocStatus::PARTIALLY_PAID]);
                $doc->statusHistories()->create([
                    'status' => DocStatus::PARTIALLY_PAID,
                    'notes' => "Partial payment recorded: {$payment->amount}",
                ]);
            }

            return $payment;
        });
    }

    /**
     * Send a document via email.
     */
    public function send(Doc $doc, string $recipientEmail, ?string $recipientName = null): void
    {
        // Mark as sent
        $doc->markAsSent("Sent to {$recipientEmail}");

        // Email sending will be handled by DocEmailService
    }

    /**
     * Clone a document.
     */
    public function clone(Doc $source, ?Model $owner = null): Doc
    {
        $type = DocType::tryFrom($source->doc_type) ?? DocType::Invoice;

        return $this->create($type, [
            'docable_type' => $source->docable_type,
            'docable_id' => $source->docable_id,
            'doc_template_id' => $source->doc_template_id,
            'due_date' => now()->addDays(config('docs.defaults.due_days', 30)),
            'currency' => $source->currency,
            'notes' => $source->notes,
            'terms' => $source->terms,
            'customer_data' => $source->customer_data,
            'company_data' => $source->company_data,
            'items' => $source->items,
            'metadata' => array_merge($source->metadata ?? [], [
                'cloned_from' => $source->id,
            ]),
        ], $owner);
    }

    /**
     * Create a version snapshot.
     */
    public function createVersion(Doc $doc, ?string $summary = null): DocVersion
    {
        $nextVersion = $doc->versions()->max('version_number') + 1;

        return $doc->versions()->create([
            'version_number' => $nextVersion,
            'snapshot' => $doc->toArray(),
            'change_summary' => $summary,
            'changed_by' => auth()->id(),
        ]);
    }

    /**
     * Calculate document totals from items.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, tax_amount: float, total: float}
     */
    public function calculateTotals(array $items, float $discountAmount = 0): array
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $itemTax = (float) ($item['tax_amount'] ?? 0);

            $subtotal += $qty * $price;
            $taxAmount += $itemTax;
        }

        $total = $subtotal + $taxAmount - $discountAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round(max(0, $total), 2),
        ];
    }
}
