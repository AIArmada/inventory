<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pre-defined commission structures that can be applied to affiliates or programs.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property array<string, mixed> $rules
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 */
class AffiliateCommissionTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
        'rules',
        'metadata',
    ];

    /**
     * Get the default template.
     */
    public static function getDefault(): ?self
    {
        return self::query()->active()->default()->first();
    }

    /**
     * Create a standard percentage template.
     */
    public static function createStandardPercentage(
        string $name,
        int $rateBasisPoints = 1000,
        bool $isDefault = false
    ): self {
        $percentageRate = $rateBasisPoints / 100;

        return self::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => "Standard {$percentageRate}% commission on all sales",
            'is_default' => $isDefault,
            'is_active' => true,
            'rules' => [
                'commission_rules' => [
                    [
                        'type' => CommissionRuleType::Affiliate->value,
                        'commission_type' => CommissionType::Percentage->value,
                        'rate' => $rateBasisPoints,
                        'conditions' => [],
                    ],
                ],
                'volume_tiers' => [],
                'mlm_overrides' => [],
            ],
        ]);
    }

    /**
     * Create a tiered volume template.
     */
    public static function createTieredVolume(
        string $name,
        int $baseRateBasisPoints = 500,
        array $volumeTiers = []
    ): self {
        $defaultTiers = empty($volumeTiers) ? [
            ['min_volume' => 0, 'max_volume' => 100000, 'bonus_rate' => 0],
            ['min_volume' => 100001, 'max_volume' => 500000, 'bonus_rate' => 100],
            ['min_volume' => 500001, 'max_volume' => 1000000, 'bonus_rate' => 200],
            ['min_volume' => 1000001, 'max_volume' => null, 'bonus_rate' => 300],
        ] : $volumeTiers;

        return self::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'Volume-based tiered commission with bonuses',
            'is_default' => false,
            'is_active' => true,
            'rules' => [
                'commission_rules' => [
                    [
                        'type' => CommissionRuleType::Affiliate->value,
                        'commission_type' => CommissionType::Percentage->value,
                        'rate' => $baseRateBasisPoints,
                        'conditions' => [],
                    ],
                ],
                'volume_tiers' => $defaultTiers,
                'mlm_overrides' => [],
            ],
        ]);
    }

    /**
     * Create an MLM template with override commissions.
     */
    public static function createMlm(
        string $name,
        int $baseRateBasisPoints = 1000,
        array $overridePercentages = [50, 25, 10]
    ): self {
        $overrides = [];
        foreach ($overridePercentages as $level => $percentage) {
            $overrides[$level + 1] = $percentage;
        }

        return self::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'MLM structure with ' . count($overridePercentages) . '-level overrides',
            'is_default' => false,
            'is_active' => true,
            'rules' => [
                'commission_rules' => [
                    [
                        'type' => CommissionRuleType::Affiliate->value,
                        'commission_type' => CommissionType::Percentage->value,
                        'rate' => $baseRateBasisPoints,
                        'conditions' => [],
                    ],
                ],
                'volume_tiers' => [],
                'mlm_overrides' => $overrides,
            ],
        ]);
    }

    public function getTable(): string
    {
        return config('affiliates.database.tables.commission_templates', 'affiliate_commission_templates');
    }

    /**
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Get commission rules as structured array.
     *
     * @return array<int, array{
     *     type: string,
     *     commission_type: string,
     *     rate: int,
     *     conditions: array<string, mixed>
     * }>
     */
    public function getCommissionRules(): array
    {
        return $this->rules['commission_rules'] ?? [];
    }

    /**
     * Get volume tiers from template.
     *
     * @return array<int, array{
     *     min_volume: int,
     *     max_volume: int|null,
     *     bonus_rate: int
     * }>
     */
    public function getVolumeTiers(): array
    {
        return $this->rules['volume_tiers'] ?? [];
    }

    /**
     * Get MLM override percentages from template.
     *
     * @return array<int, int>
     */
    public function getMlmOverrides(): array
    {
        return $this->rules['mlm_overrides'] ?? [];
    }

    /**
     * Apply this template to an affiliate.
     */
    public function applyToAffiliate(Affiliate $affiliate): void
    {
        DB::transaction(function () use ($affiliate): void {
            $rules = $this->getCommissionRules();

            // Find the base commission rule
            $baseRule = collect($rules)->firstWhere('type', CommissionRuleType::Affiliate->value);

            if ($baseRule) {
                $affiliate->update([
                    'commission_type' => $baseRule['commission_type'],
                    'commission_rate' => $baseRule['rate'],
                ]);
            }

            // Create commission rules for the affiliate
            foreach ($rules as $rule) {
                AffiliateCommissionRule::updateOrCreate(
                    [
                        'affiliate_id' => $affiliate->id,
                        'rule_type' => $rule['type'],
                    ],
                    [
                        'commission_type' => $rule['commission_type'],
                        'rate_basis_points' => $rule['rate'],
                        'conditions' => $rule['conditions'] ?? [],
                        'is_active' => true,
                    ]
                );
            }

            // Create volume tiers
            $volumeTiers = $this->getVolumeTiers();
            foreach ($volumeTiers as $tier) {
                AffiliateVolumeTier::updateOrCreate(
                    [
                        'affiliate_id' => $affiliate->id,
                        'min_volume_minor' => $tier['min_volume'],
                    ],
                    [
                        'max_volume_minor' => $tier['max_volume'],
                        'bonus_rate_basis_points' => $tier['bonus_rate'],
                        'is_active' => true,
                    ]
                );
            }
        });
    }

    /**
     * Apply this template to a program.
     */
    public function applyToProgram(AffiliateProgram $program): void
    {
        DB::transaction(function () use ($program): void {
            $rules = $this->getCommissionRules();

            // Find the base commission rule
            $baseRule = collect($rules)->firstWhere('type', CommissionRuleType::Program->value)
                ?? collect($rules)->first();

            if ($baseRule) {
                $program->update([
                    'commission_type' => $baseRule['commission_type'],
                    'default_commission_rate_basis_points' => $baseRule['rate'],
                ]);
            }
        });
    }

    protected static function booted(): void
    {
        self::creating(function (self $template): void {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });

        self::saving(function (self $template): void {
            // If this template is being set as default, unset other defaults
            if ($template->is_default && $template->isDirty('is_default')) {
                static::query()
                    ->where('id', '!=', $template->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'rules' => 'array',
            'metadata' => 'array',
        ];
    }
}
