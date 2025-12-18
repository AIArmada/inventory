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
 * @property string $program_id
 * @property string $name
 * @property int $level
 * @property int $commission_rate_basis_points
 * @property int $min_conversions
 * @property int $min_revenue
 * @property array<string, mixed>|null $benefits
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram $program
 * @property-read Collection<int, AffiliateProgramMembership> $memberships
 */
class AffiliateProgramTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'program_id',
        'name',
        'level',
        'commission_rate_basis_points',
        'min_conversions',
        'min_revenue',
        'benefits',
    ];

    protected $casts = [
        'level' => 'integer',
        'commission_rate_basis_points' => 'integer',
        'min_conversions' => 'integer',
        'min_revenue' => 'integer',
        'benefits' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.program_tiers', 'affiliate_program_tiers');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    /**
     * @return HasMany<AffiliateProgramMembership, self>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(AffiliateProgramMembership::class, 'tier_id');
    }

    public function meetsUpgradeRequirements(Affiliate $affiliate, AffiliateProgram $program): bool
    {
        $conversions = $affiliate->conversions()
            ->whereHas('attribution', function ($q) use ($program): void {
                $q->where('program_id', $program->id);
            })
            ->count();

        if ($conversions < $this->min_conversions) {
            return false;
        }

        $revenue = $affiliate->conversions()
            ->whereHas('attribution', function ($q) use ($program): void {
                $q->where('program_id', $program->id);
            })
            ->sum('total_minor');

        if ($revenue < $this->min_revenue) {
            return false;
        }

        return true;
    }

    public function getCommissionRatePercentage(): float
    {
        return $this->commission_rate_basis_points / 100;
    }

    protected static function booted(): void
    {
        static::deleting(function (self $tier): void {
            // Set tier_id to null on memberships when tier is deleted
            $tier->memberships()->update(['tier_id' => null]);
        });
    }
}
