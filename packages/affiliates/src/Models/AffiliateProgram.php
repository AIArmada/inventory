<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property ProgramStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $requires_approval
 * @property bool $is_public
 * @property int $default_commission_rate_basis_points
 * @property CommissionType $commission_type
 * @property int $cookie_lifetime_days
 * @property string|null $terms_url
 * @property array<string, mixed>|null $eligibility_rules
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, AffiliateProgramTier> $tiers
 * @property-read Collection<int, Affiliate> $affiliates
 * @property-read Collection<int, AffiliateProgramCreative> $creatives
 */
class AffiliateProgram extends Model
{
    use \AIArmada\CommerceSupport\Traits\CachesComputedValues;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'requires_approval',
        'is_public',
        'default_commission_rate_basis_points',
        'commission_type',
        'cookie_lifetime_days',
        'terms_url',
        'eligibility_rules',
        'metadata',
    ];

    protected $casts = [
        'status' => ProgramStatus::class,
        'commission_type' => CommissionType::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'requires_approval' => 'boolean',
        'is_public' => 'boolean',
        'default_commission_rate_basis_points' => 'integer',
        'cookie_lifetime_days' => 'integer',
        'eligibility_rules' => 'array',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.programs', 'affiliate_programs');
    }

    /**
     * @return HasMany<AffiliateProgramTier, self>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(AffiliateProgramTier::class, 'program_id')->orderBy('level');
    }

    /**
     * @return BelongsToMany<Affiliate, self>
     */
    public function affiliates(): BelongsToMany
    {
        return $this->belongsToMany(Affiliate::class, 'affiliate_program_memberships', 'program_id', 'affiliate_id')
            ->using(AffiliateProgramMembership::class)
            ->withPivot(['tier_id', 'status', 'applied_at', 'approved_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<AffiliateProgramCreative, self>
     */
    public function creatives(): HasMany
    {
        return $this->hasMany(AffiliateProgramCreative::class, 'program_id');
    }

    /**
     * @return HasMany<AffiliateProgramMembership, self>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(AffiliateProgramMembership::class, 'program_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== ProgramStatus::Active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isOpen(): bool
    {
        return $this->isActive() && $this->is_public;
    }

    public function canJoin(Affiliate $affiliate): bool
    {
        if (! $this->isOpen() && ! $this->requires_approval) {
            return false;
        }

        // Check if already a member
        if ($this->affiliates()->where('affiliate_id', $affiliate->id)->exists()) {
            return false;
        }

        // Check eligibility rules
        if (! empty($this->eligibility_rules)) {
            return $this->evaluateEligibility($affiliate);
        }

        return true;
    }

    public function getDefaultTier(): ?AffiliateProgramTier
    {
        return $this->cachedComputation(
            __METHOD__,
            fn () => $this->tiers()->orderBy('level', 'desc')->first()
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProgramStatus::Active)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    protected static function booted(): void
    {
        static::creating(function (self $program): void {
            if (empty($program->slug)) {
                $program->slug = Str::slug($program->name);
            }
        });
    }

    private function evaluateEligibility(Affiliate $affiliate): bool
    {
        $rules = $this->eligibility_rules ?? [];

        if (isset($rules['min_conversions'])) {
            if ($affiliate->conversions()->count() < $rules['min_conversions']) {
                return false;
            }
        }

        if (isset($rules['min_revenue'])) {
            if ($affiliate->conversions()->sum('total_minor') < $rules['min_revenue']) {
                return false;
            }
        }

        if (isset($rules['required_status']) && $affiliate->status->value !== $rules['required_status']) {
            return false;
        }

        return true;
    }
}
