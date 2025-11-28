<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\Docs\Enums\DocStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $doc_number
 * @property string $doc_type
 * @property string|null $doc_template_id
 * @property string|null $docable_type
 * @property string|null $docable_id
 * @property DocStatus $status
 * @property \Illuminate\Support\Carbon $issue_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $paid_at
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
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Doc extends Model
{
    use HasFactory;
    use HasUuids;

    public function getTable(): string
    {
        return config('docs.database.tables.docs', 'docs');
    }

    protected static function booted(): void
    {
        static::deleting(function (Doc $doc): void {
            $doc->statusHistories()->delete();
        });
    }

    protected $fillable = [
        'doc_number',
        'doc_type',
        'doc_template_id',
        'docable_type',
        'docable_id',
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

    protected $casts = [
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

    public function docable(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocTemplate::class, 'doc_template_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(DocStatusHistory::class);
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
            'paid_at' => now(),
        ]);

        $this->statusHistories()->create([
            'status' => DocStatus::PAID,
            'notes' => $notes ?? "Status changed from {$oldStatus->label()} to ".DocStatus::PAID->label(),
        ]);
    }

    public function markAsSent(?string $notes = null): void
    {
        if ($this->status === DocStatus::DRAFT || $this->status === DocStatus::PENDING) {
            $oldStatus = $this->status;

            $this->update(['status' => DocStatus::SENT]);

            $this->statusHistories()->create([
                'status' => DocStatus::SENT,
                'notes' => $notes ?? "Status changed from {$oldStatus->label()} to ".DocStatus::SENT->label(),
            ]);
        }
    }

    public function cancel(?string $notes = null): void
    {
        if ($this->status !== DocStatus::PAID) {
            $oldStatus = $this->status;

            $this->update(['status' => DocStatus::CANCELLED]);

            $this->statusHistories()->create([
                'status' => DocStatus::CANCELLED,
                'notes' => $notes ?? "Status changed from {$oldStatus->label()} to ".DocStatus::CANCELLED->label(),
            ]);
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

            $this->statusHistories()->create([
                'status' => DocStatus::OVERDUE,
                'notes' => "Status changed from {$oldStatus->label()} to ".DocStatus::OVERDUE->label()." (automatic overdue detection)",
            ]);
        }
    }
}
