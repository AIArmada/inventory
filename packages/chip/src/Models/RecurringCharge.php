<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\Chip\Enums\ChargeStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Record of each recurring charge attempt.
 *
 * @property string $id
 * @property string $schedule_id
 * @property string|null $chip_purchase_id
 * @property int $amount_minor
 * @property string $currency
 * @property ChargeStatus $status
 * @property string|null $failure_reason
 * @property Carbon $attempted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RecurringSchedule $schedule
 */
class RecurringCharge extends ChipModel
{
    public $timestamps = true;

    /**
     * @return BelongsTo<RecurringSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RecurringSchedule::class, 'schedule_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === ChargeStatus::Success;
    }

    public function isFailed(): bool
    {
        return $this->status === ChargeStatus::Failed;
    }

    public function isPending(): bool
    {
        return $this->status === ChargeStatus::Pending;
    }

    public function getAmountFormatted(): string
    {
        return number_format($this->amount_minor / 100, 2) . ' ' . $this->currency;
    }

    protected static function tableSuffix(): string
    {
        return 'recurring_charges';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChargeStatus::class,
            'attempted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
