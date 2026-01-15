<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\Enums\DocStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $doc_number
 * @property string $doc_type
 * @property string|null $doc_template_id
 * @property string|null $docable_type
 * @property string|null $docable_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property DocStatus $status
 * @property \Carbon\CarbonInterface $issue_date
 * @property \Carbon\CarbonInterface|null $due_date
 * @property \Carbon\CarbonInterface|null $paid_at
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $discount_amount
 * @property string $total
 * @property string $currency
 * @property string|null $notes
 * @property string|null $terms
 * @property array<string, mixed>|null $customer_data
 * @property array<string, mixed>|null $company_data
 * @property array<int, array<string, mixed>>|null $items
 * @property array<string, mixed>|null $metadata
 * @property string|null $pdf_path
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 * @property-read DocTemplate|null $template
 * @property-read Collection<int, DocStatusHistory> $statusHistories
 * @property-read Collection<int, DocPayment> $payments
 * @property-read Collection<int, DocVersion> $versions
 * @property-read Collection<int, DocEmail> $emails
 * @property-read Collection<int, DocApproval> $approvals
 * @property-read DocEInvoiceSubmission|null $eInvoiceSubmission
 * @property-read Model|null $docable
 */
final class Doc extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'doc_number',
        'doc_type',
        'doc_template_id',
        'docable_type',
        'docable_id',
        'owner_type',
        'owner_id',
        'status',
        'issue_date',
        'due_date',
        'paid_at',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'currency',
        'notes',
        'terms',
        'customer_data',
        'company_data',
        'items',
        'metadata',
        'pdf_path',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.docs', 'docs');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function docable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<DocTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocTemplate::class, 'doc_template_id');
    }

    /**
     * @return HasMany<DocStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DocStatusHistory::class);
    }

    /**
     * @return HasMany<DocPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DocPayment::class);
    }

    /**
     * @return HasMany<DocVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocVersion::class);
    }

    /**
     * @return HasMany<DocEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(DocEmail::class);
    }

    /**
     * @return HasMany<DocApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(DocApproval::class);
    }

    /**
     * @return HasOne<DocEInvoiceSubmission, $this>
     */
    public function eInvoiceSubmission(): HasOne
    {
        return $this->hasOne(DocEInvoiceSubmission::class);
    }

    public function isOverdue(): bool
    {
        if ($this->status === DocStatus::PAID || $this->status === DocStatus::CANCELLED) {
            return false;
        }

        return $this->due_date !== null && $this->due_date->isPast();
    }

    public function isPaid(): bool
    {
        return $this->status === DocStatus::PAID;
    }

    public function canBePaid(): bool
    {
        return $this->status->isPayable();
    }

    public function markAsPaid(?string $notes = null): void
    {
        $oldStatus = $this->status;

        $this->update([
            'status' => DocStatus::PAID,
            'paid_at' => CarbonImmutable::now(),
        ]);

        $ownerAttributes = [];
        if (config('docs.owner.enabled', false)) {
            $ownerAttributes = [
                'owner_type' => $this->owner_type,
                'owner_id' => $this->owner_id,
            ];
        }

        $this->statusHistories()->create(array_merge([
            'status' => DocStatus::PAID,
            'notes' => $notes ?? "Status changed from {$oldStatus->label()} to " . DocStatus::PAID->label(),
        ], $ownerAttributes));
    }

    public function markAsSent(?string $notes = null): void
    {
        if ($this->status === DocStatus::DRAFT || $this->status === DocStatus::PENDING) {
            $oldStatus = $this->status;

            $this->update(['status' => DocStatus::SENT]);

            $ownerAttributes = [];
            if (config('docs.owner.enabled', false)) {
                $ownerAttributes = [
                    'owner_type' => $this->owner_type,
                    'owner_id' => $this->owner_id,
                ];
            }

            $this->statusHistories()->create(array_merge([
                'status' => DocStatus::SENT,
                'notes' => $notes ?? "Status changed from {$oldStatus->label()} to " . DocStatus::SENT->label(),
            ], $ownerAttributes));
        }
    }

    public function cancel(?string $notes = null): void
    {
        if ($this->status !== DocStatus::PAID) {
            $oldStatus = $this->status;

            $this->update(['status' => DocStatus::CANCELLED]);

            $ownerAttributes = [];
            if (config('docs.owner.enabled', false)) {
                $ownerAttributes = [
                    'owner_type' => $this->owner_type,
                    'owner_id' => $this->owner_id,
                ];
            }

            $this->statusHistories()->create(array_merge([
                'status' => DocStatus::CANCELLED,
                'notes' => $notes ?? "Status changed from {$oldStatus->label()} to " . DocStatus::CANCELLED->label(),
            ], $ownerAttributes));
        }
    }

    /**
     * Update status and check for overdue
     */
    public function updateStatus(): void
    {
        if ($this->isOverdue() && $this->status !== DocStatus::OVERDUE) {
            $oldStatus = $this->status;

            $this->update(['status' => DocStatus::OVERDUE]);

            $ownerAttributes = [];
            if (config('docs.owner.enabled', false)) {
                $ownerAttributes = [
                    'owner_type' => $this->owner_type,
                    'owner_id' => $this->owner_id,
                ];
            }

            $this->statusHistories()->create(array_merge([
                'status' => DocStatus::OVERDUE,
                'notes' => "Status changed from {$oldStatus->label()} to " . DocStatus::OVERDUE->label() . ' (automatic overdue detection)',
            ], $ownerAttributes));
        }
    }

    protected static function booted(): void
    {
        self::deleting(function (Doc $doc): void {
            $doc->statusHistories()->delete();
            $doc->payments()->delete();
            $doc->versions()->delete();
            $doc->emails()->delete();
            $doc->approvals()->delete();
            $doc->eInvoiceSubmission?->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'customer_data' => 'array',
            'company_data' => 'array',
            'items' => 'array',
            'metadata' => 'array',
        ];
    }
}
