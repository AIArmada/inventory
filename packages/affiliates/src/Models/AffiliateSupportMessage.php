<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ticket_id
 * @property string|null $affiliate_id
 * @property string|null $staff_id
 * @property string $message
 * @property bool $is_staff_reply
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateSupportTicket $ticket
 * @property-read Affiliate|null $affiliate
 */
class AffiliateSupportMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'ticket_id',
        'affiliate_id',
        'staff_id',
        'message',
        'is_staff_reply',
    ];

    protected $casts = [
        'is_staff_reply' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.support_messages', 'affiliate_support_messages');
    }

    /**
     * @return BelongsTo<AffiliateSupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(AffiliateSupportTicket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }
}
