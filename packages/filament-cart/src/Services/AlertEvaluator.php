<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Models\AlertRule;
use Illuminate\Support\Collection;

/**
 * Evaluate alert conditions against events.
 */
class AlertEvaluator
{
    /**
     * Evaluate if an alert rule matches the event data.
     *
     * @param  array<string, mixed>  $eventData
     */
    public function evaluate(AlertRule $rule, array $eventData): bool
    {
        $conditions = $rule->conditions;

        if (empty($conditions)) {
            return true; // No conditions means always match
        }

        // Check for "all" conditions (AND)
        if (isset($conditions['all'])) {
            return $this->evaluateAllConditions($conditions['all'], $eventData);
        }

        // Check for "any" conditions (OR)
        if (isset($conditions['any'])) {
            return $this->evaluateAnyConditions($conditions['any'], $eventData);
        }

        // Single condition object
        if (isset($conditions['field'])) {
            return $this->evaluateSingleCondition($conditions, $eventData);
        }

        // Array of conditions (default to AND)
        if (is_array($conditions) && isset($conditions[0])) {
            return $this->evaluateAllConditions($conditions, $eventData);
        }

        return true;
    }

    /**
     * Get all matching rules for an event type and data.
     *
     * @param  array<string, mixed>  $eventData
     * @return Collection<int, AlertRule>
     */
    public function getMatchingRules(string $eventType, array $eventData): Collection
    {
        return AlertRule::query()
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get()
            ->filter(function (AlertRule $rule) use ($eventData) {
                // Skip if in cooldown
                if ($rule->isInCooldown()) {
                    return false;
                }

                return $this->evaluate($rule, $eventData);
            });
    }

    /**
     * Check if a rule should be throttled.
     */
    public function shouldThrottle(AlertRule $rule): bool
    {
        return $rule->isInCooldown();
    }

    /**
     * Evaluate all conditions (AND logic).
     *
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $eventData
     */
    private function evaluateAllConditions(array $conditions, array $eventData): bool
    {
        foreach ($conditions as $condition) {
            if (! $this->evaluateSingleCondition($condition, $eventData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate any conditions (OR logic).
     *
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $eventData
     */
    private function evaluateAnyConditions(array $conditions, array $eventData): bool
    {
        foreach ($conditions as $condition) {
            if ($this->evaluateSingleCondition($condition, $eventData)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate a single condition.
     *
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $eventData
     */
    private function evaluateSingleCondition(array $condition, array $eventData): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if ($field === null) {
            return true;
        }

        // Get the actual value from event data (supports dot notation)
        $actualValue = data_get($eventData, $field);

        return $this->compareValues($actualValue, $operator, $value);
    }

    /**
     * Compare values based on operator.
     */
    private function compareValues(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=', '==' => $actual === $expected,
            '===' => $actual === $expected,
            '!=', '<>' => $actual !== $expected,
            '!==' => $actual !== $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected),
            'not_in' => is_array($expected) && ! in_array($actual, $expected),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'starts_with' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            'ends_with' => is_string($actual) && is_string($expected) && str_ends_with($actual, $expected),
            'is_null' => $actual === null,
            'is_not_null' => $actual !== null,
            'is_empty' => empty($actual),
            'is_not_empty' => ! empty($actual),
            'regex' => is_string($actual) && is_string($expected) && preg_match($expected, $actual) === 1,
            'between' => is_array($expected) && count($expected) >= 2 && $actual >= $expected[0] && $actual <= $expected[1],
            default => $actual === $expected,
        };
    }
}
