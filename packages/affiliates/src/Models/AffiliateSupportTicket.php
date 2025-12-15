<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $subject
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read Collection<int, AffiliateSupportMessage> $messages
 */
final class AffiliateSupportTicket extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'subject',
        'category',
        'priority',
        'status',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.support_tickets', 'affiliate_support_tickets');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    /**
     * @return HasMany<AffiliateSupportMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AffiliateSupportMessage::class, 'ticket_id')
            ->orderBy('created_at');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $ticket): void {
            $ticket->messages()->delete();
        });
    }
}
