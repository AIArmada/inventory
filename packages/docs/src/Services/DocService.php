<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Models\DocVersion;
use AIArmada\Docs\Numbering\NumberStrategyRegistry;
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\PartiallyPaid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Core document management service.
 *
 * Handles document creation, updates, PDF generation, payments, versioning, and conversions.
 */
final class DocService implements DocServiceInterface
{
    public function __construct(
        protected NumberStrategyRegistry $numberRegistry,
        protected SequenceManager $sequenceManager,
    ) {}

    /**
     * Generate a document number for the given type.
     */
    public function generateNumber(string $docType = 'invoice'): string
    {
        return $this->numberRegistry->generate($docType);
    }

    /**
     * Resolve the storage disk for a document type.
     */
    public function resolveStorageDiskForDocType(string $docType): string
    {
        return $this->resolveStorageDisk($docType);
    }

    /**
     * Create a new document from DocData DTO.
     */
    public function create(DocData $data): Doc
    {
        $docType = $data->docType ?? 'invoice';

        // Generate doc number if not provided
        $docNumber = $data->docNumber ?? $this->generateNumber($docType);

        // Resolve current owner
        $owner = $this->resolveOwner();

        // Get template (scoped by owner if enabled)
        $template = null;
        if ($data->docTemplateId) {
            $template = $this->getTemplateQuery()->find($data->docTemplateId);

            if (! $template) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'doc_template_id' => __('Invalid template selection.'),
                ]);
            }
        } elseif ($data->templateSlug) {
            $template = $this->getTemplateQuery()->where('slug', $data->templateSlug)->first();
        }

        if (! $template) {
            $template = $this->getTemplateQuery()
                ->where('is_default', true)
                ->where('doc_type', $docType)
                ->first();
        }

        // Calculate totals (use provided values if available, otherwise calculate)
        $calculatedSubtotal = $this->calculateSubtotal($data->items);
        $subtotal = $data->subtotal ?? $calculatedSubtotal;
        $taxAmount = $data->taxAmount ?? ($subtotal * ($data->taxRate ?? 0));
        $discountAmount = $data->discountAmount ?? 0;
        $total = $data->total ?? ($subtotal + $taxAmount - $discountAmount);

        // Merge metadata with pdf options (if provided)
        $metadata = $data->metadata ?? [];
        if ($data->pdfOptions !== null) {
            $metadata['pdf'] = array_merge($metadata['pdf'] ?? [], $data->pdfOptions);
        }

        // Determine status
        $status = $data->status ?? Draft::class;

        if ($status instanceof DocStatus) {
            $status = $status::class;
        }

        if (! is_string($status)) {
            $status = Draft::class;
        }

        $statusClass = DocStatus::resolveStateClassFor($status);

        // Only set due_date for payable statuses (not for PAID, CANCELLED, REFUNDED)
        $dueDate = $data->dueDate;
        if ($dueDate === null && DocStatus::fromString($statusClass)->isPayable()) {
            $dueDays = (int) $this->resolveDefault($docType, 'due_days', 30);
            $dueDate = CarbonImmutable::now()->addDays($dueDays);
        }

        // Build doc data with owner columns if enabled
        $docData = [
            'doc_number' => $docNumber,
            'doc_type' => $docType,
            'doc_template_id' => $template?->id,
            'docable_type' => $data->docableType,
            'docable_id' => $data->docableId,
            'status' => $statusClass,
            'issue_date' => $data->issueDate ?? CarbonImmutable::now(),
            'due_date' => $dueDate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'currency' => $data->currency ?? (string) $this->resolveDefault($docType, 'currency', 'MYR'),
            'notes' => $data->notes,
            'terms' => $data->terms,
            'customer_data' => $data->customerData,
            'company_data' => $data->companyData ?? config('docs.company'),
            'items' => $data->items,
            'metadata' => $metadata,
        ];

        // Add owner columns if enabled
        if ($owner !== null) {
            $docData['owner_type'] = $owner->getMorphClass();
            $docData['owner_id'] = $owner->getKey();
        }

        // Create doc
        $doc = Doc::create($docData);

        // Load relationships
        $doc->loadMissing(['template', 'docable']);

        // Generate PDF if requested
        if ($data->generatePdf ?? false) {
            $this->generatePdf($doc);
        }

        return $doc;
    }

    /**
     * Create a new document from array data with DocType enum.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromType(DocType $type, array $data, ?Model $owner = null): Doc
    {
        return DB::transaction(function () use ($type, $data, $owner): Doc {
            // Generate document number
            $docNumber = $this->sequenceManager->generate($type, $owner);

            $docData = array_merge($data, [
                'doc_number' => $docNumber,
                'doc_type' => $type->value,
                'status' => Draft::class,
                'issue_date' => $data['issue_date'] ?? CarbonImmutable::now(),
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
                    (float) ($data['discount_amount'] ?? $doc->discount_amount)
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
        $sourceType = $source->doc_type instanceof DocType
            ? $source->doc_type
            : DocType::tryFrom($source->doc_type);

        // Validate conversion is allowed
        $allowedSources = $targetType->getConversionSources();
        if ($sourceType && ! in_array($sourceType, $allowedSources, true)) {
            throw new InvalidArgumentException(
                "Cannot convert {$sourceType->label()} to {$targetType->label()}"
            );
        }

        // Create new document from source
        return $this->createFromType($targetType, [
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
            $ownerAttributes = [];
            if (config('docs.owner.enabled', false)) {
                $ownerAttributes = [
                    'owner_type' => $doc->owner_type,
                    'owner_id' => $doc->owner_id,
                ];
            }

            $payment = $doc->payments()->create(array_merge($paymentData, [
                'paid_at' => $paymentData['paid_at'] ?? CarbonImmutable::now(),
                'currency' => $paymentData['currency'] ?? $doc->currency,
            ], $ownerAttributes));

            // Update document status based on payments
            $totalPaid = $doc->payments()->sum('amount');
            $docTotal = (float) $doc->total;

            if ($totalPaid >= $docTotal) {
                $doc->markAsPaid("Payment recorded: {$payment->amount}");
            } elseif ($totalPaid > 0) {
                $doc->update(['status' => PartiallyPaid::class]);
                $doc->statusHistories()->create(array_merge([
                    'status' => PartiallyPaid::class,
                    'notes' => "Partial payment recorded: {$payment->amount}",
                ], $ownerAttributes));
            }

            return $payment;
        });
    }

    /**
     * Clone a document.
     */
    public function clone(Doc $source, ?Model $owner = null): Doc
    {
        $val = $source->doc_type;
        $type = ($val instanceof DocType ? $val : DocType::tryFrom($val)) ?? DocType::Invoice;

        return $this->createFromType($type, [
            'docable_type' => $source->docable_type,
            'docable_id' => $source->docable_id,
            'doc_template_id' => $source->doc_template_id,
            'due_date' => CarbonImmutable::now()->addDays(config('docs.defaults.due_days', 30)),
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

        $ownerAttributes = [];
        if (config('docs.owner.enabled', false)) {
            $ownerAttributes = [
                'owner_type' => $doc->owner_type,
                'owner_id' => $doc->owner_id,
            ];
        }

        return $doc->versions()->create(array_merge([
            'version_number' => $nextVersion,
            'snapshot' => $doc->toArray(),
            'change_summary' => $summary,
            'changed_by' => auth()->id(),
        ], $ownerAttributes));
    }

    /**
     * Generate a PDF for a document.
     *
     * @return string The relative path to the stored PDF (or raw PDF content if $save is false)
     */
    public function generatePdf(Doc $doc, bool $save = true): string
    {
        // Load the polymorphic relationship to access ticket/order data
        $doc->loadMissing('docable');

        $docType = $doc->doc_type ?? 'invoice';
        $template = $doc->template ?? $this->getTemplateQueryForDoc($doc)
            ->where('is_default', true)
            ->where('doc_type', $docType)
            ->first();
        $viewName = $template->view_name ?? config("docs.types.{$docType}.default_template", "{$docType}-default");
        $resolvedView = $this->normalizeViewName($viewName);

        // Resolve effective PDF options (config defaults overridden by per-doc metadata)
        $defaults = [
            'format' => config('docs.pdf.format', 'a4'),
            'orientation' => config('docs.pdf.orientation', 'portrait'),
            'margin' => [
                'top' => config('docs.pdf.margin.top', 10),
                'right' => config('docs.pdf.margin.right', 10),
                'bottom' => config('docs.pdf.margin.bottom', 10),
                'left' => config('docs.pdf.margin.left', 10),
            ],
            'full_bleed' => config('docs.pdf.full_bleed', false),
            'print_background' => config('docs.pdf.print_background', true),
        ];
        $templatePdf = (array) ($template->settings['pdf'] ?? []);
        $perDoc = (array) ($doc->metadata['pdf'] ?? []);
        // Precedence: config < template < per-doc
        $opts = array_replace_recursive($defaults, $templatePdf, $perDoc);

        $pdf = Pdf::view($resolvedView, [
            'doc' => $doc,
        ])
            ->format($opts['format'])
            ->orientation($opts['orientation'])
            ->margins(
                $opts['margin']['top'],
                $opts['margin']['right'],
                $opts['margin']['bottom'],
                $opts['margin']['left']
            );

        // Enable borderless full-bleed if configured
        if (! empty($opts['full_bleed'])) {
            $pdf->margins(0, 0, 0, 0);
        }

        // Ensure backgrounds (colors, gradients) are printed
        if (! empty($opts['print_background'])) {
            $pdf->withBrowsershot(static function ($browsershot): void {
                $browsershot->showBackground();
            });
        }

        if ($save) {
            $path = $this->generatePdfPath($doc);
            $disk = $this->resolveStorageDisk($docType);

            Storage::disk($disk)->put($path, $pdf->generatePdfContent());

            $doc->update(['pdf_path' => $path]);

            // Return relative path (consumer can turn into URL). Avoid assuming url() method exists on custom disk.
            return $path;
        }

        return $pdf->generatePdfContent();
    }

    /**
     * Download or retrieve PDF path for a document.
     *
     * @return string The relative path to the PDF
     */
    public function downloadPdf(Doc $doc): string
    {
        $docType = $doc->doc_type ?? 'invoice';

        if ($doc->pdf_path && Storage::disk($this->resolveStorageDisk($docType))->exists($doc->pdf_path)) {
            return $doc->pdf_path;
        }

        return $this->generatePdf($doc);
    }

    /**
     * Mark a document as sent (typically after emailing).
     */
    public function markAsSent(Doc $doc, ?string $notes = null): void
    {
        $doc->markAsSent($notes);
    }

    /**
     * Update a document's status with audit trail.
     */
    public function updateStatus(Doc $doc, DocStatus | string $status, ?string $notes = null): void
    {
        $oldStatus = $doc->status;
        $statusClass = DocStatus::resolveStateClassFor($status, $doc);

        $doc->update(['status' => $statusClass]);

        $ownerAttributes = [];
        if (config('docs.owner.enabled', false)) {
            $ownerAttributes = [
                'owner_type' => $doc->owner_type,
                'owner_id' => $doc->owner_id,
            ];
        }

        // Record status change
        $doc->statusHistories()->create(array_merge([
            'status' => $statusClass,
            'notes' => $notes ?? "Status changed from {$oldStatus->label()} to " . DocStatus::labelFor($statusClass, $doc),
        ], $ownerAttributes));
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
            // Support both 'price' and 'unit_price' keys
            $price = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
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

    /**
     * Get template query builder scoped to the document's owner columns.
     *
     * @return Builder<DocTemplate>
     */
    protected function getTemplateQueryForDoc(Doc $doc): Builder
    {
        $query = DocTemplate::query();

        if (! config('docs.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = (bool) config('docs.owner.include_global', false);

        if ($doc->owner_type !== null && $doc->owner_id !== null) {
            return $query->where(function (Builder $builder) use ($doc, $includeGlobal): void {
                $builder->where('owner_type', $doc->owner_type)
                    ->where('owner_id', $doc->owner_id);

                if ($includeGlobal) {
                    $builder->orWhere(function (Builder $inner): void {
                        $inner->whereNull('owner_type')->whereNull('owner_id');
                    });
                }
            });
        }

        return $query->whereNull('owner_type')->whereNull('owner_id');
    }

    /**
     * Normalize a template view name into the canonical 'docs::templates.<slug>' form.
     */
    protected function normalizeViewName(string $viewName): string
    {
        $viewName = mb_trim($viewName);

        // Already correct
        if (str_starts_with($viewName, 'docs::templates.')) {
            return $viewName;
        }

        // If it has the docs:: prefix but missing templates.
        if (str_starts_with($viewName, 'docs::')) {
            $suffix = mb_substr($viewName, mb_strlen('docs::')) ?: '';
            if ($suffix === '') {
                return 'docs::templates.doc-default';
            }
            if (str_starts_with($suffix, 'templates.')) {
                return 'docs::' . $suffix; // becomes docs::templates.<slug>
            }

            return 'docs::templates.' . $suffix; // ensure templates prefix
        }

        // Dot notation like docs.templates.slug
        if (str_starts_with($viewName, 'docs.templates.')) {
            $slug = mb_substr($viewName, mb_strlen('docs.templates.')) ?: 'doc-default';

            return 'docs::templates.' . $slug;
        }

        // Starting with templates.
        if (str_starts_with($viewName, 'templates.')) {
            $slug = mb_substr($viewName, mb_strlen('templates.')) ?: 'doc-default';

            return 'docs::templates.' . $slug;
        }

        // Fallback plain slug
        return 'docs::templates.' . $viewName;
    }

    /**
     * Calculate subtotal from items (simple sum).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function calculateSubtotal(array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? $item['unit_price'] ?? 0;
            $subtotal += $quantity * $price;
        }

        return $subtotal;
    }

    protected function generatePdfPath(Doc $doc): string
    {
        $docType = $doc->doc_type ?? 'invoice';
        $basePath = $this->resolveStoragePath($docType);
        $filename = $this->normalizePdfFilename($doc);

        $basePath = mb_trim($basePath, '/');

        return $basePath === '' ? $filename : "{$basePath}/{$filename}";
    }

    protected function normalizePdfFilename(Doc $doc): string
    {
        $raw = (string) ($doc->doc_number ?: $doc->getKey());

        // Prevent path traversal / separator injection.
        $raw = str_replace(['/', '\\'], '-', $raw);

        // Keep only safe filename characters.
        $sanitized = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $raw);
        $sanitized = mb_trim($sanitized, " .-_/\t\n\r\0\x0B");

        while (str_contains($sanitized, '..')) {
            $sanitized = str_replace('..', '.', $sanitized);
        }

        // Treat remaining dots as unsafe path-ish tokens.
        $sanitized = str_replace('.', '-', $sanitized);

        // Collapse repeated separators.
        $sanitized = (string) preg_replace('/[-_]{2,}/', '-', $sanitized);
        $sanitized = mb_trim($sanitized, '-_ ');

        if ($sanitized === '') {
            $sanitized = (string) $doc->getKey();
        }

        // Avoid extremely long filenames.
        $sanitized = Str::limit($sanitized, 120, '');

        return $sanitized . '.pdf';
    }

    protected function resolveStorageDisk(string $docType): string
    {
        return config("docs.types.{$docType}.storage.disk")
            ?? config("docs.storage.disks.{$docType}")
            ?? config('docs.storage.disk', 'local');
    }

    protected function resolveStoragePath(string $docType): string
    {
        return config("docs.types.{$docType}.storage.path")
            ?? config("docs.storage.paths.{$docType}")
            ?? config('docs.storage.path', 'docs');
    }

    protected function resolveDefault(string $docType, string $key, mixed $fallback = null): mixed
    {
        return config("docs.types.{$docType}.defaults.{$key}", config("docs.defaults.{$key}", $fallback));
    }

    /**
     * Resolve the current owner from the configured resolver.
     */
    protected function resolveOwner(): ?Model
    {
        if (! config('docs.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    /**
     * Get template query builder with owner scoping applied.
     *
     * @return Builder<DocTemplate>
     */
    protected function getTemplateQuery(): Builder
    {
        $query = DocTemplate::query();

        if (! config('docs.owner.enabled', false)) {
            return $query;
        }

        $owner = $this->resolveOwner();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        return $query->forOwner($owner, $includeGlobal);
    }
}
