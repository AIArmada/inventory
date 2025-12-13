<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string $trigger_type
 * @property int $trigger_delay_minutes
 * @property int $max_attempts
 * @property int $attempt_interval_hours
 * @property int|null $min_cart_value_cents
 * @property int|null $max_cart_value_cents
 * @property int|null $min_items
 * @property int|null $max_items
 * @property array<string>|null $target_segments
 * @property array<string>|null $exclude_segments
 * @property string $strategy
 * @property bool $offer_discount
 * @property string|null $discount_type
 * @property int|null $discount_value
 * @property bool $offer_free_shipping
 * @property int|null $urgency_hours
 * @property bool $ab_testing_enabled
 * @property int $ab_test_split_percent
 * @property string|null $control_template_id
 * @property string|null $variant_template_id
 * @property int $total_targeted
 * @property int $total_sent
 * @property int $total_opened
 * @property int $total_clicked
 * @property int $total_recovered
 * @property int $recovered_revenue_cents
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RecoveryAttempt> $attempts
 * @property-read RecoveryTemplate|null $controlTemplate
 * @property-read RecoveryTemplate|null $variantTemplate
 */
class RecoveryCampaign extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'trigger_type',
        'trigger_delay_minutes',
        'max_attempts',
        'attempt_interval_hours',
        'min_cart_value_cents',
        'max_cart_value_cents',
        'min_items',
        'max_items',
        'target_segments',
        'exclude_segments',
        'strategy',
        'offer_discount',
        'discount_type',
        'discount_value',
        'offer_free_shipping',
        'urgency_hours',
        'ab_testing_enabled',
        'ab_test_split_percent',
        'control_template_id',
        'variant_template_id',
        'total_targeted',
        'total_sent',
        'total_opened',
        'total_clicked',
        'total_recovered',
        'recovered_revenue_cents',
        'starts_at',
        'ends_at',
        'last_run_at',
    ];

    public function getTable(): string
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $prefix . 'recovery_campaigns';
    }

    /**
     * @return HasMany<RecoveryAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(RecoveryAttempt::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<RecoveryTemplate, $this>
     */
    public function controlTemplate(): BelongsTo
    {
        return $this->belongsTo(RecoveryTemplate::class, 'control_template_id');
    }

    /**
     * @return BelongsTo<RecoveryTemplate, $this>
     */
    public function variantTemplate(): BelongsTo
    {
        return $this->belongsTo(RecoveryTemplate::class, 'variant_template_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function getOpenRate(): float
    {
        return $this->total_sent > 0
            ? $this->total_opened / $this->total_sent
            : 0.0;
    }

    public function getClickRate(): float
    {
        return $this->total_sent > 0
            ? $this->total_clicked / $this->total_sent
            : 0.0;
    }

    public function getConversionRate(): float
    {
        return $this->total_sent > 0
            ? $this->total_recovered / $this->total_sent
            : 0.0;
    }

    public function getAverageRecoveredValue(): int
    {
        return $this->total_recovered > 0
            ? (int) ($this->recovered_revenue_cents / $this->total_recovered)
            : 0;
    }

    protected static function booted(): void
    {
        static::deleting(function (RecoveryCampaign $campaign): void {
            // Cancel scheduled attempts
            $campaign->attempts()
                ->where('status', 'scheduled')
                ->update(['status' => 'cancelled']);
        });
    }

    protected function casts(): array
    {
        return [
            'target_segments' => 'array',
            'exclude_segments' => 'array',
            'offer_discount' => 'boolean',
            'offer_free_shipping' => 'boolean',
            'ab_testing_enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }
}
