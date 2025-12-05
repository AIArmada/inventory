<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Enums\RecurringStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * App-layer recurring payment schedule using Chip's token + charge APIs.
 *
 * @property string $id
 * @property string $chip_client_id
 * @property string $recurring_token_id
 * @property string|null $subscriber_type
 * @property string|null $subscriber_id
 * @property RecurringStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property RecurringInterval $interval
 * @property int $interval_count
 * @property Carbon|null $next_charge_at
 * @property Carbon|null $last_charged_at
 * @property int $failure_count
 * @property int $max_failures
 * @property Carbon|null $cancelled_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RecurringCharge> $charges
 */
class RecurringSchedule extends ChipModel
{
    public $timestamps = true;

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<RecurringCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(RecurringCharge::class, 'schedule_id');
    }

    public function isActive(): bool
    {
        return $this->status === RecurringStatus::Active;
    }

    public function isPaused(): bool
    {
        return $this->status === RecurringStatus::Paused;
    }

    public function isCancelled(): bool
    {
        return $this->status === RecurringStatus::Cancelled;
    }

    public function isFailed(): bool
    {
        return $this->status === RecurringStatus::Failed;
    }

    public function isDue(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return $this->next_charge_at !== null && $this->next_charge_at->isPast();
    }

    public function calculateNextChargeDate(): Carbon
    {
        $base = $this->last_charged_at ?? now();

        return match ($this->interval) {
            RecurringInterval::Daily => $base->copy()->addDays($this->interval_count),
            RecurringInterval::Weekly => $base->copy()->addWeeks($this->interval_count),
            RecurringInterval::Monthly => $base->copy()->addMonths($this->interval_count),
            RecurringInterval::Yearly => $base->copy()->addYears($this->interval_count),
        };
    }

    public function getAmountFormatted(): string
    {
        return number_format($this->amount_minor / 100, 2) . ' ' . $this->currency;
    }

    protected static function tableSuffix(): string
    {
        return 'recurring_schedules';
    }

    protected static function booted(): void
    {
        static::deleting(function (self $schedule): void {
            $schedule->charges()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecurringStatus::class,
            'interval' => RecurringInterval::class,
            'next_charge_at' => 'datetime',
            'last_charged_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
