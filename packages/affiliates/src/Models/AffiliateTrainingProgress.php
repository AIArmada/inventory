<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $module_id
 * @property int $progress_percent
 * @property int|null $last_position
 * @property int|null $quiz_score
 * @property int $quiz_attempts
 * @property string|null $certificate_url
 * @property Carbon|null $completed_at
 * @property Carbon|null $quiz_passed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read AffiliateTrainingModule $module
 */
final class AffiliateTrainingProgress extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'module_id',
        'progress_percent',
        'last_position',
        'quiz_score',
        'quiz_attempts',
        'certificate_url',
        'completed_at',
        'quiz_passed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'last_position' => 'integer',
        'quiz_score' => 'integer',
        'quiz_attempts' => 'integer',
        'completed_at' => 'datetime',
        'quiz_passed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.training_progress', 'affiliate_training_progress');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    /**
     * @return BelongsTo<AffiliateTrainingModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(AffiliateTrainingModule::class, 'module_id');
    }
}
