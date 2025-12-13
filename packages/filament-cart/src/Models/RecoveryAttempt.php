<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $cart_id
 * @property string|null $template_id
 * @property string|null $recipient_email
 * @property string|null $recipient_phone
 * @property string|null $recipient_name
 * @property string $channel
 * @property string $status
 * @property int $attempt_number
 * @property bool $is_control
 * @property bool $is_variant
 * @property string|null $discount_code
 * @property int|null $discount_value_cents
 * @property bool $free_shipping_offered
 * @property \Illuminate\Support\Carbon|null $offer_expires_at
 * @property int $cart_value_cents
 * @property int $cart_items_count
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $opened_at
 * @property \Illuminate\Support\Carbon|null $clicked_at
 * @property \Illuminate\Support\Carbon|null $converted_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property string|null $message_id
 * @property array<string, mixed>|null $metadata
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read RecoveryCampaign $campaign
 * @property-read Cart|null $cart
 * @property-read RecoveryTemplate|null $template
 */
class RecoveryAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'cart_id',
        'template_id',
        'recipient_email',
        'recipient_phone',
        'recipient_name',
        'channel',
        'status',
        'attempt_number',
        'is_control',
        'is_variant',
        'discount_code',
        'discount_value_cents',
        'free_shipping_offered',
        'offer_expires_at',
        'cart_value_cents',
        'cart_items_count',
        'scheduled_for',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'converted_at',
        'failed_at',
        'message_id',
        'metadata',
        'failure_reason',
    ];

    public function getTable(): string
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $prefix . 'recovery_attempts';
    }

    /**
     * @return BelongsTo<RecoveryCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RecoveryCampaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    /**
     * @return BelongsTo<RecoveryTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RecoveryTemplate::class, 'template_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'opened', 'clicked', 'converted']);
    }

    public function isOpened(): bool
    {
        return in_array($this->status, ['opened', 'clicked', 'converted']);
    }

    public function isClicked(): bool
    {
        return in_array($this->status, ['clicked', 'converted']);
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'bounced']);
    }

    public function markAsSent(?string $messageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'message_id' => $messageId,
        ]);
    }

    public function markAsOpened(): void
    {
        if ($this->opened_at === null) {
            $this->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        }
    }

    public function markAsClicked(): void
    {
        if ($this->clicked_at === null) {
            $this->markAsOpened();
            $this->update([
                'status' => 'clicked',
                'clicked_at' => now(),
            ]);
        }
    }

    public function markAsConverted(): void
    {
        $this->markAsClicked();
        $this->update([
            'status' => 'converted',
            'converted_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    protected function casts(): array
    {
        return [
            'is_control' => 'boolean',
            'is_variant' => 'boolean',
            'free_shipping_offered' => 'boolean',
            'offer_expires_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'converted_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
