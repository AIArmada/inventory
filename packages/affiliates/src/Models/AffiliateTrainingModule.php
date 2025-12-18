<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string|null $content
 * @property string $type
 * @property string|null $video_url
 * @property array|null $resources
 * @property array|null $quiz
 * @property int|null $passing_score
 * @property int $duration_minutes
 * @property int $sort_order
 * @property bool $is_required
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AffiliateTrainingProgress> $progress
 */
class AffiliateTrainingModule extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'content',
        'type',
        'video_url',
        'resources',
        'quiz',
        'passing_score',
        'duration_minutes',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'resources' => 'array',
        'quiz' => 'array',
        'passing_score' => 'integer',
        'duration_minutes' => 'integer',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.training_modules', 'affiliate_training_modules');
    }

    /**
     * @return HasMany<AffiliateTrainingProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(AffiliateTrainingProgress::class, 'module_id');
    }

    protected static function booted(): void
    {
        self::deleting(function (self $module): void {
            $module->progress()->delete();
        });
    }
}
