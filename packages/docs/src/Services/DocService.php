<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Numbering\NumberStrategyRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

class DocService
{
    public function __construct(
        protected NumberStrategyRegistry $numberRegistry
    ) {}

    public function generateDocNumber(string $docType = 'invoice'): string
    {
        return $this->numberRegistry->generate($docType);
    }

    public function createDoc(DocData $data): Doc
    {
        $docType = $data->docType ?? 'invoice';

        // Generate doc number if not provided
        $docNumber = $data->docNumber ?? $this->generateDocNumber($docType);

        // Resolve current owner
        $owner = $this->resolveOwner();

        // Get template (scoped by owner if enabled)
        $template = null;
        if ($data->docTemplateId) {
            $template = $this->getTemplateQuery()->find($data->docTemplateId);
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
        $status = $data->status ?? DocStatus::DRAFT;

        // Only set due_date for payable statuses (not for PAID, CANCELLED, REFUNDED)
        $dueDate = $data->dueDate;
        if ($dueDate === null && $status->isPayable()) {
            $dueDays = (int) $this->resolveDefault($docType, 'due_days', 30);
            $dueDate = now()->addDays($dueDays);
        }

        // Build doc data with owner columns if enabled
        $docData = [
            'doc_number' => $docNumber,
            'doc_type' => $docType,
            'doc_template_id' => $template?->id,
            'docable_type' => $data->docableType,
            'docable_id' => $data->docableId,
            'status' => $status,
            'issue_date' => $data->issueDate ?? now(),
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

    public function generatePdf(Doc $doc, bool $save = true): string
    {
        // Load the polymorphic relationship to access ticket/order data
        $doc->loadMissing('docable');

        $docType = $doc->doc_type ?? 'invoice';
        $template = $doc->template ?? $this->getTemplateQuery()
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
            // Call on underlying Browsershot instance
            $pdf->getBrowsershot()->showBackground();
        }

        if ($save) {
            $path = $this->generatePdfPath($doc);
            $disk = $this->resolveStorageDisk($docType);

            Storage::disk($disk)->put($path, $pdf->getBrowsershot()->pdf());

            $doc->update(['pdf_path' => $path]);

            // Return relative path (consumer can turn into URL). Avoid assuming url() method exists on custom disk.
            return $path;
        }

        return $pdf->getBrowsershot()->pdf();
    }

    public function downloadPdf(Doc $doc): string
    {
        $docType = $doc->doc_type ?? 'invoice';

        if ($doc->pdf_path && Storage::disk($this->resolveStorageDisk($docType))->exists($doc->pdf_path)) {
            return $doc->pdf_path;
        }

        return $this->generatePdf($doc);
    }

    public function emailDoc(Doc $doc, string $email): void
    {
        // This would integrate with your mail system
        // Implementation depends on your mail setup
        $doc->markAsSent();
    }

    public function updateDocStatus(Doc $doc, DocStatus $status, ?string $notes = null): void
    {
        $oldStatus = $doc->status;

        $doc->update(['status' => $status]);

        // Record status change
        $doc->statusHistories()->create([
            'status' => $status,
            'notes' => $notes ?? "Status changed from {$oldStatus->label()} to {$status->label()}",
        ]);
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
     * Calculate subtotal from items
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function calculateSubtotal(array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $subtotal += $quantity * $price;
        }

        return $subtotal;
    }

    protected function generatePdfPath(Doc $doc): string
    {
        $docType = $doc->doc_type ?? 'invoice';
        $basePath = $this->resolveStoragePath($docType);
        $filename = $doc->doc_number . '.pdf';

        return "{$basePath}/{$filename}";
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

        return app(OwnerResolverInterface::class)->resolve();
    }

    /**
     * Get template query builder with owner scoping applied.
     *
     * @return Builder<DocTemplate>
     */
    protected function getTemplateQuery(): Builder
    {
        $query = DocTemplate::query();

        if (config('docs.owner.enabled', false)) {
            $owner = $this->resolveOwner();
            $includeGlobal = config('docs.owner.include_global', true);

            if ($owner !== null) {
                if ($includeGlobal) {
                    $query->where(function ($q) use ($owner): void {
                        $q->where('owner_type', $owner->getMorphClass())
                            ->where('owner_id', $owner->getKey())
                            ->orWhere(function ($q): void {
                                $q->whereNull('owner_type')
                                    ->whereNull('owner_id');
                            });
                    });
                } else {
                    $query->where('owner_type', $owner->getMorphClass())
                        ->where('owner_id', $owner->getKey());
                }
            } elseif ($includeGlobal) {
                $query->whereNull('owner_type')->whereNull('owner_id');
            }
        }

        return $query;
    }
}
