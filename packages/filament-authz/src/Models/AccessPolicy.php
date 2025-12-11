<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\FilamentAuthz\Enums\ConditionOperator;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $effect
 * @property string $target_action
 * @property string|null $target_resource
 * @property array<array{attribute: string, operator: string, value: mixed, source?: string}> $conditions
 * @property int $priority
 * @property bool $is_active
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property array<string, mixed>|null $metadata
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AccessPolicy extends Model
{
    use HasOwner;
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'effect',
        'target_action',
        'target_resource',
        'conditions',
        'priority',
        'is_active',
        'valid_from',
        'valid_until',
        'metadata',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('filament-authz.database.tables.access_policies', 'authz_access_policies');

        return $table;
    }

    /**
     * Get the policy effect as enum.
     */
    public function getEffectEnum(): PolicyEffect
    {
        return PolicyEffect::tryFrom($this->effect) ?? PolicyEffect::Deny;
    }

    /**
     * Check if this policy is currently valid (within validity period).
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from !== null && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until !== null && $now->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this policy applies to the given action and resource.
     */
    public function appliesTo(string $action, ?string $resource = null): bool
    {
        // Check action match (supports wildcards)
        if (! $this->matchesAction($action)) {
            return false;
        }

        // Check resource match (supports wildcards)
        if ($resource !== null && ! $this->matchesResource($resource)) {
            return false;
        }

        return true;
    }

    /**
     * Evaluate this policy against the given context.
     *
     * @param  array<string, mixed>  $context
     */
    public function evaluate(array $context): PolicyDecision
    {
        // Check if policy is valid
        if (! $this->isValid()) {
            return PolicyDecision::NotApplicable;
        }

        // Evaluate all conditions
        $conditionsResult = $this->evaluateConditions($context);

        if ($conditionsResult === null) {
            return PolicyDecision::NotApplicable;
        }

        if (! $conditionsResult) {
            return PolicyDecision::NotApplicable;
        }

        // All conditions passed, return effect
        return $this->getEffectEnum() === PolicyEffect::Allow
            ? PolicyDecision::Permit
            : PolicyDecision::Deny;
    }

    /**
     * Evaluate all conditions against the context.
     *
     * @param  array<string, mixed>  $context
     */
    public function evaluateConditions(array $context): ?bool
    {
        $conditions = $this->conditions;

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $result = $this->evaluateCondition($condition, $context);

            if ($result === null) {
                // Indeterminate - missing data
                return null;
            }

            if (! $result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope to filter active policies.
     *
     * @param  Builder<AccessPolicy>  $query
     * @return Builder<AccessPolicy>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter currently valid policies.
     *
     * @param  Builder<AccessPolicy>  $query
     * @return Builder<AccessPolicy>
     */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $now);
            });
    }

    /**
     * Scope to filter by target action.
     *
     * @param  Builder<AccessPolicy>  $query
     * @return Builder<AccessPolicy>
     */
    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where(function (Builder $q) use ($action): void {
            $q->where('target_action', $action)
                ->orWhere('target_action', '*');

            // Handle wildcard patterns like 'orders.*'
            $parts = explode('.', $action);
            if (count($parts) > 1) {
                $q->orWhere('target_action', $parts[0] . '.*');
            }
        });
    }

    /**
     * Scope to filter by target resource.
     *
     * @param  Builder<AccessPolicy>  $query
     * @return Builder<AccessPolicy>
     */
    public function scopeForResource(Builder $query, ?string $resource): Builder
    {
        return $query->where(function (Builder $q) use ($resource): void {
            $q->whereNull('target_resource')
                ->orWhere('target_resource', '*');

            if ($resource !== null) {
                $q->orWhere('target_resource', $resource);

                // Handle wildcard patterns
                $parts = explode('.', $resource);
                if (count($parts) > 1) {
                    $q->orWhere('target_resource', $parts[0] . '.*');
                }
            }
        });
    }

    /**
     * Scope to order by priority.
     *
     * @param  Builder<AccessPolicy>  $query
     * @return Builder<AccessPolicy>
     */
    public function scopeOrderByPriority(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('priority', $direction);
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('filament-authz.owner.enabled', false)) {
            return $query;
        }

        if (! $owner) {
            return $includeGlobal
                ? $query->whereNull('owner_id')
                : $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    /**
     * Check if this policy's target action matches the given action.
     */
    protected function matchesAction(string $action): bool
    {
        $targetAction = $this->target_action;

        // Exact match
        if ($targetAction === $action) {
            return true;
        }

        // Wildcard match
        if ($targetAction === '*') {
            return true;
        }

        // Prefix wildcard match (e.g., 'orders.*' matches 'orders.create')
        if (str_ends_with($targetAction, '.*')) {
            $prefix = mb_substr($targetAction, 0, -2);

            return str_starts_with($action, $prefix . '.');
        }

        return false;
    }

    /**
     * Check if this policy's target resource matches the given resource.
     */
    protected function matchesResource(?string $resource): bool
    {
        $targetResource = $this->target_resource;

        // No resource restriction
        if ($targetResource === null || $targetResource === '*') {
            return true;
        }

        // Exact match
        if ($targetResource === $resource) {
            return true;
        }

        // Prefix wildcard match
        if (str_ends_with($targetResource, '.*')) {
            $prefix = mb_substr($targetResource, 0, -2);

            return str_starts_with((string) $resource, $prefix . '.');
        }

        return false;
    }

    /**
     * Evaluate a single condition.
     *
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $context
     */
    protected function evaluateCondition(array $condition, array $context): ?bool
    {
        $attribute = $condition['attribute'] ?? null;
        $operatorString = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? null;
        $source = $condition['source'] ?? 'subject';

        if ($attribute === null) {
            return true;
        }

        // Get attribute value from appropriate context source
        $sourceContext = $context[$source] ?? $context;
        $attributeValue = data_get($sourceContext, $attribute);

        // Try to use enum operator
        $operator = ConditionOperator::tryFrom($operatorString);

        if ($operator !== null) {
            return $operator->evaluate($attributeValue, $value);
        }

        // Fallback to basic comparison
        return $attributeValue === $value;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'metadata' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }
}
