<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Vouchers\Campaigns\Enums\CampaignObjective;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Enums\CampaignType;
use AIArmada\Vouchers\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property CampaignType $type
 * @property CampaignObjective $objective
 * @property int|null $budget_cents
 * @property int $spent_cents
 * @property int|null $max_redemptions
 * @property int $current_redemptions
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property string $timezone
 * @property bool $ab_testing_enabled
 * @property string|null $ab_winner_variant
 * @property \Illuminate\Support\Carbon|null $ab_winner_declared_at
 * @property CampaignStatus $status
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metrics
 * @property array<string, mixed>|null $automation_rules
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collection<int, CampaignVariant> $variants
 * @property-read Collection<int, CampaignEvent> $events
 * @property-read Collection<int, Voucher> $vouchers
 * @property-read int|null $remaining_budget
 * @property-read float|null $budget_utilization
 * @property-read int|null $remaining_redemptions
 */
class Campaign extends Model
{
    use HasOwner;
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'objective',
        'budget_cents',
        'spent_cents',
        'max_redemptions',
        'current_redemptions',
        'starts_at',
        'ends_at',
        'timezone',
        'ab_testing_enabled',
        'ab_winner_variant',
        'ab_winner_declared_at',
        'status',
        'owner_type',
        'owner_id',
        'metrics',
        'automation_rules',
    ];

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('vouchers.table_names.campaigns', 'voucher_campaigns');

        return $table;
    }

    /**
     * @return HasMany<CampaignVariant, Campaign>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(CampaignVariant::class, 'campaign_id');
    }

    /**
     * @return HasMany<CampaignEvent, Campaign>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class, 'campaign_id');
    }

    /**
     * @return HasMany<Voucher, Campaign>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'campaign_id');
    }

    /**
     * Scope to filter active campaigns.
     *
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->where('status', CampaignStatus::Active->value)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Scope to filter by status.
     *
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeWithStatus(Builder $query, CampaignStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Scope to filter by type.
     *
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeOfType(Builder $query, CampaignType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * Check if campaign is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== CampaignStatus::Active) {
            return false;
        }

        $now = Carbon::now($this->timezone);

        if ($this->starts_at !== null && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * Check if campaign has started.
     */
    public function hasStarted(): bool
    {
        if ($this->starts_at === null) {
            return true;
        }

        return $this->starts_at->lte(Carbon::now($this->timezone));
    }

    /**
     * Check if campaign has ended.
     */
    public function hasEnded(): bool
    {
        if ($this->ends_at === null) {
            return false;
        }

        return $this->ends_at->lt(Carbon::now($this->timezone));
    }

    /**
     * Check if campaign has budget remaining.
     */
    public function hasBudgetRemaining(): bool
    {
        if ($this->budget_cents === null) {
            return true;
        }

        return $this->spent_cents < $this->budget_cents;
    }

    /**
     * Get remaining budget in cents.
     */
    public function getRemainingBudgetAttribute(): ?int
    {
        if ($this->budget_cents === null) {
            return null;
        }

        return max(0, $this->budget_cents - $this->spent_cents);
    }

    /**
     * Get budget utilization percentage.
     */
    public function getBudgetUtilizationAttribute(): ?float
    {
        if ($this->budget_cents === null || $this->budget_cents === 0) {
            return null;
        }

        return ($this->spent_cents / $this->budget_cents) * 100;
    }

    /**
     * Check if campaign has redemptions remaining.
     */
    public function hasRedemptionsRemaining(): bool
    {
        if ($this->max_redemptions === null) {
            return true;
        }

        return $this->current_redemptions < $this->max_redemptions;
    }

    /**
     * Get remaining redemptions.
     */
    public function getRemainingRedemptionsAttribute(): ?int
    {
        if ($this->max_redemptions === null) {
            return null;
        }

        return max(0, $this->max_redemptions - $this->current_redemptions);
    }

    /**
     * Check if campaign can receive traffic.
     */
    public function canReceiveTraffic(): bool
    {
        return $this->isActive()
            && $this->hasBudgetRemaining()
            && $this->hasRedemptionsRemaining();
    }

    /**
     * Transition to a new status.
     */
    public function transitionTo(CampaignStatus $status): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        return $this->save();
    }

    /**
     * Activate the campaign.
     */
    public function activate(): bool
    {
        return $this->transitionTo(CampaignStatus::Active);
    }

    /**
     * Pause the campaign.
     */
    public function pause(): bool
    {
        return $this->transitionTo(CampaignStatus::Paused);
    }

    /**
     * Complete the campaign.
     */
    public function complete(): bool
    {
        return $this->transitionTo(CampaignStatus::Completed);
    }

    /**
     * Cancel the campaign.
     */
    public function cancel(): bool
    {
        return $this->transitionTo(CampaignStatus::Cancelled);
    }

    /**
     * Record spending against budget.
     */
    public function recordSpending(int $amountCents): void
    {
        $this->increment('spent_cents', $amountCents);
    }

    /**
     * Record a redemption.
     */
    public function recordRedemption(): void
    {
        $this->increment('current_redemptions');
    }

    /**
     * Get the control variant.
     */
    public function getControlVariant(): ?CampaignVariant
    {
        return $this->variants()->where('is_control', true)->first();
    }

    /**
     * Get the winning variant if A/B test is concluded.
     */
    public function getWinningVariant(): ?CampaignVariant
    {
        if ($this->ab_winner_variant === null) {
            return null;
        }

        return $this->variants()->where('variant_code', $this->ab_winner_variant)->first();
    }

    /**
     * Declare a winner variant for A/B testing.
     */
    public function declareWinner(CampaignVariant $variant): void
    {
        $this->update([
            'ab_winner_variant' => $variant->variant_code,
            'ab_winner_declared_at' => Carbon::now(),
        ]);
    }

    /**
     * Application-level cascade delete.
     */
    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign): void {
            if (empty($campaign->slug)) {
                $campaign->slug = Str::slug($campaign->name);
            }
        });

        static::deleting(function (Campaign $campaign): void {
            $campaign->variants()->delete();
            $campaign->events()->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => CampaignType::class,
            'objective' => CampaignObjective::class,
            'status' => CampaignStatus::class,
            'budget_cents' => 'integer',
            'spent_cents' => 'integer',
            'max_redemptions' => 'integer',
            'current_redemptions' => 'integer',
            'ab_testing_enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ab_winner_declared_at' => 'datetime',
            'metrics' => 'array',
            'automation_rules' => 'array',
        ];
    }
}
