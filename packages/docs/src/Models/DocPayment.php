<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks payments made against documents.
 *
 * @property string $id
 * @property string $doc_id
 * @property string $amount
 * @property string $currency
 * @property string $payment_method
 * @property string|null $reference
 * @property string|null $transaction_id
 * @property Carbon $paid_at
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Doc $doc
 */
final class DocPayment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'doc_id',
        'amount',
        'currency',
        'payment_method',
        'reference',
        'transaction_id',
        'paid_at',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_payments', 'docs_payments');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
