<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Database\Factories\ConditionFactory;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Condition model for creating reusable condition configurations.
 *
 * This model stores conditions that can be used to quickly create cart
 * conditions with predefined settings.
 *
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $id
 * @property string $name
 * @property string|null $display_name
 * @property string|null $description
 * @property string $type
 * @property string $target
 * @property array<mixed>|null $target_definition
 * @property string $value
 * @property string|null $operator
 * @property bool $is_charge
 * @property bool $is_dynamic
 * @property bool $is_discount
 * @property bool $is_percentage
 * @property string|null $parsed_value
 * @property int $order
 * @property array<mixed>|null $attributes
 * @property array{factory_keys?: array<int, string>, context?: array<string, mixed>}|null $rules
 * @property bool $is_active
 * @property bool $is_global
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class Condition extends Model
{
    /** @use HasFactory<ConditionFactory> */
    use HasFactory;

    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'cart.owner';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'display_name',
        'description',
        'type',
        'target',
        'target_definition',
        'value',
        'operator',
        'is_charge',
        'is_dynamic',
        'is_discount',
        'is_percentage',
        'parsed_value',
        'order',
        'attributes',
        'rules',
        'is_active',
        'is_global',
    ];

    /**
     * @param  array{factory_keys?: array<int, string>, context?: array<string, mixed>}|null  $rules
     * @return array{factory_keys: array<int, string>, context: array<string, mixed>}|null
     */
    public static function normalizeRulesDefinition(?array $rules, bool $isDynamic): ?array
    {
        if (! $isDynamic || empty($rules)) {
            return null;
        }

        $factoryKeys = [];
        if (isset($rules['factory_keys']) && is_array($rules['factory_keys'])) {
            $factoryKeys = array_values(array_filter(
                $rules['factory_keys'],
                static fn (mixed $key): bool => is_string($key) && $key !== '' // @phpstan-ignore function.alreadyNarrowedType
            ));
        }

        if ($factoryKeys === []) {
            return null;
        }

        $context = [];
        if (isset($rules['context']) && is_array($rules['context'])) {
            foreach ($rules['context'] as $key => $value) {
                if ($key === '') {
                    continue;
                }

                $normalizedValue = self::normalizeContextValue($value);

                if ($normalizedValue === null) {
                    continue;
                }

                $context[$key] = $normalizedValue;
            }
        }

        return [
            'factory_keys' => $factoryKeys,
            'context' => $context,
        ];
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('cart.database.conditions_table', 'conditions');
    }

    /**
     * Compute derived fields from the value.
     */
    public function computeDerivedFields(): void
    {
        // Parse the value to extract operator and numeric value
        $value = (string) $this->value;

        // Determine operator
        if (str_contains($value, '%')) {
            $this->operator = '%';
            $this->is_percentage = true;
            $this->parsed_value = (string) ((float) mb_rtrim($value, '%') / 100);
        } elseif (str_starts_with($value, '+')) {
            $this->operator = '+';
            $this->is_percentage = false;
            $this->parsed_value = mb_ltrim($value, '+');
        } elseif (str_starts_with($value, '-')) {
            $this->operator = '-';
            $this->is_percentage = false;
            $this->parsed_value = $value;
        } elseif (str_starts_with($value, '*')) {
            $this->operator = '*';
            $this->is_percentage = false;
            $this->parsed_value = mb_ltrim($value, '*');
        } elseif (str_starts_with($value, '/')) {
            $this->operator = '/';
            $this->is_percentage = false;
            $this->parsed_value = mb_ltrim($value, '/');
        } else {
            // No operator, assume addition
            $this->operator = '+';
            $this->is_percentage = false;
            $this->parsed_value = $value;
        }

        // Determine if it's a discount or charge
        $numericValue = (float) $this->parsed_value;
        if ($this->operator === '%') {
            $this->is_discount = $numericValue < 0;
            $this->is_charge = $numericValue > 0;
        } else {
            $this->is_discount = $this->operator === '-';
            $this->is_charge = $this->operator === '+';
        }

        // Check if dynamic (has rules)
        $factoryKeys = $this->getRuleFactoryKeys();

        $this->is_dynamic = ! empty($factoryKeys);

        if (! $this->is_dynamic) {
            $this->rules = null;
        }
    }

    /**
     * Check if this condition is a discount type.
     */
    public function isDiscount(): bool
    {
        return $this->is_discount;
    }

    /**
     * Check if this condition is a charge/fee.
     */
    public function isCharge(): bool
    {
        return $this->is_charge;
    }

    /**
     * Check if the condition is a fee.
     */
    public function isFee(): bool
    {
        return in_array($this->type, ['fee', 'surcharge']);
    }

    /**
     * Check if the condition is a tax.
     */
    public function isTax(): bool
    {
        return $this->type === 'tax';
    }

    /**
     * Check if the condition is for shipping.
     */
    public function isShipping(): bool
    {
        return $this->type === 'shipping';
    }

    /**
     * Check if value is a percentage.
     */
    public function isPercentage(): bool
    {
        return $this->is_percentage;
    }

    /**
     * Check if this is a dynamic condition.
     */
    public function isDynamic(): bool
    {
        return $this->is_dynamic;
    }

    /**
     * @return array<int, string>
     */
    public function getRuleFactoryKeys(): array
    {
        if (! is_array($this->rules)) {
            return [];
        }

        $keys = $this->rules['factory_keys'] ?? [];

        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_filter($keys, static function ($key): bool {
            return $key !== '';
        }));
    }

    /**
     * @return array<string, mixed>
     */
    public function getRuleContext(): array
    {
        if (! is_array($this->rules)) {
            return [];
        }

        $context = $this->rules['context'] ?? [];

        return is_array($context) ? $context : [];
    }

    /**
     * Check if this condition is global (auto-applied).
     */
    public function isGlobal(): bool
    {
        return $this->is_global;
    }

    /**
     * Get formatted value for display.
     */
    public function getFormattedValueAttribute(): string
    {
        if ($this->isPercentage()) {
            return $this->value;
        }

        $rawValue = $this->value;
        $normalized = mb_ltrim($rawValue, '+');
        $money = Money::{$this->resolveCurrency()}($normalized);

        return str_starts_with($rawValue, '+')
            ? '+' . $money
            : (string) $money;
    }

    /**
     * Convert condition to CartCondition data array.
     *
     * @return array<string, mixed>
     */
    public function toConditionArray(?string $customName = null): array
    {
        $factoryKeys = $this->getRuleFactoryKeys();
        $targetDefinition = ConditionTarget::from($this->target)->toArray();

        return [
            'name' => $customName ?? $this->display_name,
            'type' => $this->type,
            'target' => $this->target,
            'target_definition' => $targetDefinition,
            'value' => $this->value,
            'order' => $this->order,
            'attributes' => array_merge($this->attributes ?? [], [
                'condition_id' => $this->id,
                'condition_name' => $this->name,
                'is_global' => $this->is_global,
            ]),
            'rules' => $this->is_dynamic ? [
                'factory_keys' => $factoryKeys,
                'context' => $this->getRuleContext(),
            ] : null,
            'is_global' => $this->is_global,
        ];
    }

    /**
     * Create a CartCondition instance from this condition.
     */
    public function createCondition(?string $customName = null): CartCondition
    {
        $data = $this->toConditionArray($customName);

        $rules = $this->buildRuleCallables();

        $target = ConditionTarget::from($this->target);

        return new CartCondition(
            name: $data['name'],
            type: $data['type'],
            target: $target,
            value: $data['value'],
            attributes: $data['attributes'],
            order: $data['order'],
            rules: $rules
        );
    }

    /**
     * Set the rules attribute (raw storage).
     *
     * @param  array{factory_keys?: array<int, string>, context?: array<string, mixed>}|null  $rules
     */
    public function setRulesAttribute(?array $rules): void
    {
        // Store as JSON string - will be normalized during save
        $this->attributes['rules'] = json_encode($rules);
    }

    /**
     * Scope query to the specified owner.
     *
     * When null is explicitly passed, this returns global-only records
     * (owner_type IS NULL AND owner_id IS NULL). This overrides the base
     * trait behavior which would fall back to OwnerContext::resolve().
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to, or null for global-only
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('cart.owner.enabled', false)) {
            return $query;
        }

        if ($owner === null) {
            return $query->withoutOwnerScope()
                ->whereNull('owner_type')
                ->whereNull('owner_id');
        }

        $includeGlobal = $includeGlobal && (bool) config('cart.owner.include_global', false);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    /**
     * Boot the model and set up event listeners.
     */
    protected static function booted(): void
    {
        self::saving(function (Condition $condition): void {
            $condition->computeDerivedFields();
            $condition->target_definition = ConditionTarget::from($condition->target)->toArray();

            // Normalize rules after is_dynamic is computed
            if (isset($condition->attributes['rules']) && is_string($condition->attributes['rules'])) {
                $rawRules = json_decode($condition->attributes['rules'], true);
                if (is_array($rawRules)) {
                    $condition->attributes['rules'] = json_encode(
                        self::normalizeRulesDefinition($rawRules, $condition->is_dynamic)
                    );
                }
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'attributes' => 'array',
            'rules' => 'array',
            'target_definition' => 'array',
            'is_charge' => 'boolean',
            'is_dynamic' => 'boolean',
            'is_discount' => 'boolean',
            'is_percentage' => 'boolean',
            'is_active' => 'boolean',
            'is_global' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Scope to filter active conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope to filter by condition type.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function ofType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Scope to filter by condition target.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function forItems(Builder $query): void
    {
        $query->where('target', 'item');
    }

    /**
     * Scope to filter discounts.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function discounts(Builder $query): void
    {
        $query->where('is_discount', true);
    }

    /**
     * Scope to filter charges/fees.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function charges(Builder $query): void
    {
        $query->where('is_charge', true);
    }

    /**
     * Scope to filter dynamic conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function dynamic(Builder $query): void
    {
        $query->where('is_dynamic', true);
    }

    /**
     * Scope to filter global conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function global(Builder $query): void
    {
        $query->where('is_global', true)
            ->where('is_active', true);
    }

    /**
     * Scope to filter percentage-based conditions.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function percentageBased(Builder $query): void
    {
        $query->where('is_percentage', true);
    }

    /**
     * @return array<callable>|null
     */
    protected function buildRuleCallables(): ?array
    {
        $factoryKeys = $this->getRuleFactoryKeys();

        if ($factoryKeys === []) {
            return null;
        }

        $rulesFactory = app(RulesFactoryInterface::class);
        $context = $this->getRuleContext();

        $rules = [];

        foreach ($factoryKeys as $factoryKey) {
            if (! $rulesFactory->canCreateRules($factoryKey)) {
                throw new InvalidArgumentException("Unsupported rule factory key [{$factoryKey}]");
            }

            $rules = array_merge(
                $rules,
                $rulesFactory->createRules($factoryKey, ['context' => $context])
            );
        }

        return $rules;
    }

    protected function resolveCurrency(): string
    {
        return mb_strtoupper(config('cart.money.default_currency', 'USD'));
    }

    private static function normalizeContextValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::normalizeContextValue($item), $value);
        }

        if (is_bool($value) || (is_numeric($value) && ! is_string($value))) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = Str::of($value)->trim()->toString();

        if ($trimmed === '') {
            return null;
        }

        $lower = mb_strtolower($trimmed);
        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        if (str_contains($trimmed, ',')) {
            return array_values(array_filter(
                array_map(static fn (string $segment): mixed => self::normalizeContextValue($segment), explode(',', $trimmed)),
                static fn (mixed $segment): bool => ! (is_string($segment) && $segment === ''),
            ));
        }

        if (is_numeric($trimmed)) {
            if (str_contains($trimmed, '.') || str_contains($trimmed, 'e') || str_contains($trimmed, 'E')) {
                return (float) $trimmed;
            }

            return (int) $trimmed;
        }

        return $trimmed;
    }
}
