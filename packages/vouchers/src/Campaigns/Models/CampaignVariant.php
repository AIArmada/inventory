<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Models;

use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string|null $voucher_id
 * @property string $name
 * @property string $variant_code
 * @property int $traffic_percentage
 * @property int $impressions
 * @property int $applications
 * @property int $conversions
 * @property int $revenue_cents
 * @property int $discount_cents
 * @property bool $is_control
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Voucher|null $voucher
 * @property-read Collection<int, CampaignEvent> $events
 * @property-read float $conversion_rate
 * @property-read float $application_rate
 * @property-read float|null $average_order_value
 * @property-read int $net_revenue
 */
class CampaignVariant extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'voucher_id',
        'name',
        'variant_code',
        'traffic_percentage',
        'impressions',
        'applications',
        'conversions',
        'revenue_cents',
        'discount_cents',
        'is_control',
    ];

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['campaign_variants'] ?? $prefix.'voucher_campaign_variants';
    }

    /**
     * @return BelongsTo<Campaign, CampaignVariant>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<Voucher, CampaignVariant>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    /**
     * @return HasMany<CampaignEvent, CampaignVariant>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class, 'variant_id');
    }

    /**
     * Scope to filter control variants.
     *
     * @param  Builder<CampaignVariant>  $query
     * @return Builder<CampaignVariant>
     */
    public function scopeControl(Builder $query): Builder
    {
        return $query->where('is_control', true);
    }

    /**
     * Scope to filter treatment variants.
     *
     * @param  Builder<CampaignVariant>  $query
     * @return Builder<CampaignVariant>
     */
    public function scopeTreatment(Builder $query): Builder
    {
        return $query->where('is_control', false);
    }

    /**
     * Scope to order by conversion rate.
     *
     * @param  Builder<CampaignVariant>  $query
     * @return Builder<CampaignVariant>
     */
    public function scopeOrderByConversionRate(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderByRaw(
            "CASE WHEN applications > 0 THEN CAST(conversions AS FLOAT) / applications ELSE 0 END {$direction}"
        );
    }

    /**
     * Get conversion rate (conversions / applications).
     */
    public function getConversionRateAttribute(): float
    {
        if ($this->applications === 0) {
            return 0.0;
        }

        return ($this->conversions / $this->applications) * 100;
    }

    /**
     * Get application rate (applications / impressions).
     */
    public function getApplicationRateAttribute(): float
    {
        if ($this->impressions === 0) {
            return 0.0;
        }

        return ($this->applications / $this->impressions) * 100;
    }

    /**
     * Get average order value.
     */
    public function getAverageOrderValueAttribute(): ?float
    {
        if ($this->conversions === 0) {
            return null;
        }

        return $this->revenue_cents / $this->conversions;
    }

    /**
     * Get net revenue (revenue minus discounts).
     */
    public function getNetRevenueAttribute(): int
    {
        return $this->revenue_cents - $this->discount_cents;
    }

    /**
     * Record an impression.
     */
    public function recordImpression(): void
    {
        $this->increment('impressions');
    }

    /**
     * Record an application.
     */
    public function recordApplication(): void
    {
        $this->increment('applications');
    }

    /**
     * Record a conversion with revenue and discount.
     */
    public function recordConversion(int $revenueCents, int $discountCents): void
    {
        $this->increment('conversions');
        $this->increment('revenue_cents', $revenueCents);
        $this->increment('discount_cents', $discountCents);
    }

    /**
     * Calculate statistical significance against control variant.
     *
     * Uses a simplified Z-test for proportion comparison.
     *
     * @return array{z_score: float, p_value: float, significant: bool}|null
     */
    public function calculateSignificance(self $control): ?array
    {
        // Need minimum sample size for meaningful analysis
        if ($this->applications < 30 || $control->applications < 30) {
            return null;
        }

        $p1 = $this->conversions / $this->applications;
        $p2 = $control->conversions / $control->applications;
        $n1 = $this->applications;
        $n2 = $control->applications;

        // Pooled proportion
        $pPooled = ($this->conversions + $control->conversions) / ($n1 + $n2);

        if ($pPooled === 0.0 || $pPooled === 1.0) {
            return null;
        }

        // Standard error
        $se = sqrt($pPooled * (1 - $pPooled) * (1 / $n1 + 1 / $n2));

        if ($se === 0.0) {
            return null;
        }

        // Z-score
        $z = ($p1 - $p2) / $se;

        // Two-tailed p-value approximation using error function
        $pValue = 2 * (1 - $this->normalCdf(abs($z)));

        return [
            'z_score' => round($z, 4),
            'p_value' => round($pValue, 4),
            'significant' => $pValue < 0.05,
        ];
    }

    /**
     * Compare performance to another variant.
     *
     * @return array{conversion_lift: float, revenue_lift: float, aov_lift: float|null}
     */
    public function compareToVariant(self $other): array
    {
        $conversionLift = 0.0;
        if ($other->conversion_rate > 0) {
            $conversionLift = (($this->conversion_rate - $other->conversion_rate) / $other->conversion_rate) * 100;
        }

        $revenueLift = 0.0;
        if ($other->revenue_cents > 0) {
            $revenueLift = (($this->revenue_cents - $other->revenue_cents) / $other->revenue_cents) * 100;
        }

        $aovLift = null;
        $otherAov = $other->average_order_value;
        $thisAov = $this->average_order_value;
        if ($otherAov !== null && $otherAov > 0 && $thisAov !== null) {
            $aovLift = (($thisAov - $otherAov) / $otherAov) * 100;
        }

        return [
            'conversion_lift' => round($conversionLift, 2),
            'revenue_lift' => round($revenueLift, 2),
            'aov_lift' => $aovLift !== null ? round($aovLift, 2) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'traffic_percentage' => 'integer',
            'impressions' => 'integer',
            'applications' => 'integer',
            'conversions' => 'integer',
            'revenue_cents' => 'integer',
            'discount_cents' => 'integer',
            'is_control' => 'boolean',
        ];
    }

    /**
     * Approximate normal CDF using error function.
     */
    private function normalCdf(float $z): float
    {
        return 0.5 * (1 + $this->erf($z / sqrt(2)));
    }

    /**
     * Approximate error function.
     */
    private function erf(float $x): float
    {
        // Approximation constants
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $sign = $x < 0 ? -1 : 1;
        $x = abs($x);

        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);

        return $sign * $y;
    }
}
