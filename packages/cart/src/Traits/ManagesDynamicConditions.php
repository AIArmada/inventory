<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Exceptions\InvalidCartConditionException;
use Closure;
use Exception;
use InvalidArgumentException;
use Throwable;

trait ManagesDynamicConditions
{
    /**
     * Initialize the dynamic conditions collection if not already done.
     */
    protected CartConditionCollection $dynamicConditions;

    /**
     * Rules factory for reconstructing dynamic conditions from metadata.
     */
    protected ?RulesFactoryInterface $rulesFactory = null;

    /**
     * Optional handler invoked when a dynamic condition fails to evaluate or restore.
     *
     * @var (callable(string, ?CartCondition, ?Throwable, array<string, mixed>): void)|null
     */
    protected $dynamicConditionFailureHandler = null;

    /**
     * Flag indicating dynamic conditions need re-evaluation.
     *
     * This implements a dirty-flag pattern to debounce condition evaluation.
     * Instead of evaluating after every cart modification, we mark conditions
     * as dirty and evaluate only when totals are requested.
     */
    protected bool $dynamicConditionsDirty = false;

    /**
     * Set rules factory for dynamic condition persistence.
     *
     * @param  RulesFactoryInterface  $factory  Factory to create rule closures
     */
    public function setRulesFactory(RulesFactoryInterface $factory): static
    {
        $this->rulesFactory = $factory;

        return $this;
    }

    /**
     * Get the current rules factory.
     */
    public function getRulesFactory(): ?RulesFactoryInterface
    {
        return $this->rulesFactory;
    }

    /**
     * Register a dynamic condition that will be automatically applied/removed based on its rules.
     *
     * Smart detection supports multiple input types:
     * - CartCondition with rules already set
     * - String factory key (creates rules via factory)
     * - Array of factory keys (combines multiple rule sets)
     * - Closure that returns rules (evaluated automatically)
     *
     * @param  CartCondition|array<string, mixed>  $condition  The dynamic condition or condition data
     * @param  array<callable>|string|array<string>|Closure|null  $rules  Rules as array, factory key(s), or closure
     * @param  string|array<string>|null  $ruleFactoryKey  Optional explicit key(s) for persistence
     * @param  array<string, mixed>  $metadata  Additional metadata to persist alongside the condition
     *
     * @throws InvalidArgumentException When invalid parameters are provided
     */
    public function registerDynamicCondition(
        CartCondition | array $condition,
        array | string | Closure | null $rules = null,
        string | array | null $ruleFactoryKey = null,
        array $metadata = []
    ): static {
        // Handle smart condition creation
        if (is_array($condition)) {
            $condition = $this->createConditionFromArray($condition, $rules, $ruleFactoryKey, $metadata);
        }

        if (! $condition->isDynamic()) {
            throw new InvalidArgumentException('Only dynamic conditions (with rules) can be registered.');
        }

        $this->initializeDynamicConditions();
        $this->dynamicConditions->put($condition->getName(), $condition);

        // Persist metadata if rule factory key provided
        if ($ruleFactoryKey !== null) {
            $this->persistDynamicConditionMetadata($condition, $ruleFactoryKey, $metadata);
        }

        // Mark as dirty instead of evaluating immediately
        $this->markDynamicConditionsDirty();

        return $this;
    }

    /**
     * Register a callback that will be invoked when dynamic condition evaluation fails.
     *
     * @param  callable(string, ?CartCondition, ?Throwable, array<string, mixed>): void  $handler
     */
    public function onDynamicConditionFailure(callable $handler): static
    {
        $this->dynamicConditionFailureHandler = $handler;

        return $this;
    }

    /**
     * Get all registered dynamic conditions.
     */
    public function getDynamicConditions(): CartConditionCollection
    {
        $this->initializeDynamicConditions();

        return $this->dynamicConditions;
    }

    /**
     * Remove a dynamic condition.
     *
     * @param  string  $name  Name of the dynamic condition to remove
     */
    public function removeDynamicCondition(string $name): static
    {
        $this->initializeDynamicConditions();
        $this->dynamicConditions->forget($name);

        // Also remove it from active conditions if it was applied
        $this->removeCondition($name);

        // Remove from metadata storage
        $this->removeDynamicConditionMetadata($name);

        return $this;
    }

    /**
     * Evaluate all dynamic conditions and apply/remove them based on their rules.
     *
     * This method clears the dirty flag before evaluation to prevent
     * infinite recursion when evaluation triggers getItems() which
     * would otherwise re-trigger evaluation.
     */
    public function evaluateDynamicConditions(): void
    {
        // Clear the dirty flag BEFORE evaluation to prevent infinite recursion
        // when evaluation calls methods that trigger getItems() (e.g., addItemCondition)
        $this->dynamicConditionsDirty = false;

        $this->initializeDynamicConditions();

        foreach ($this->dynamicConditions as $condition) {
            $targetDefinition = $condition->getTargetDefinition();

            if ($targetDefinition->scope === ConditionScope::ITEMS) {
                $this->evaluateItemScopedDynamicCondition($condition);

                continue;
            }

            $this->evaluateCartScopedDynamicCondition($condition);
        }
    }

    /**
     * Mark dynamic conditions as needing re-evaluation.
     *
     * Call this instead of evaluateDynamicConditions() when making
     * cart modifications. The actual evaluation will be deferred until
     * totals are requested, avoiding N evaluations in loops.
     */
    public function markDynamicConditionsDirty(): void
    {
        $this->dynamicConditionsDirty = true;
    }

    /**
     * Evaluate dynamic conditions only if they are dirty.
     *
     * This should be called before calculating totals to ensure
     * conditions are up-to-date while avoiding unnecessary evaluations.
     */
    public function evaluateDynamicConditionsIfDirty(): void
    {
        if ($this->dynamicConditionsDirty) {
            $this->evaluateDynamicConditions();
        }
    }

    /**
     * Check if dynamic conditions need re-evaluation.
     */
    public function isDynamicConditionsDirty(): bool
    {
        return $this->dynamicConditionsDirty;
    }

    /**
     * Restore dynamic conditions from metadata storage.
     */
    public function restoreDynamicConditions(): static
    {
        if ($this->rulesFactory === null) {
            return $this; // No factory, can't restore
        }

        $metadata = $this->storage->getMetadata(
            $this->getIdentifier(),
            $this->instance(),
            'dynamic_conditions'
        );

        if (empty($metadata) || ! is_array($metadata)) {
            return $this; // No conditions to restore
        }

        foreach ($metadata as $name => $conditionData) {
            if (! isset($conditionData['rule_factory_key'])) {
                continue; // Skip conditions without factory keys
            }

            $ruleFactoryKey = $conditionData['rule_factory_key'];

            try {
                // Handle both single key and array of keys
                $rules = $this->restoreRulesFromFactoryKey($ruleFactoryKey, $conditionData);

                $targetPayload = $this->resolveDynamicConditionTarget($conditionData);

                $condition = new CartCondition(
                    name: $name,
                    type: $conditionData['type'] ?? 'unknown',
                    target: $targetPayload,
                    value: $conditionData['value'] ?? 0,
                    attributes: $conditionData['attributes'] ?? [],
                    order: $conditionData['order'] ?? 0,
                    rules: $rules
                );

                // Register without persistence to avoid recursive metadata updates
                $this->initializeDynamicConditions();
                $this->dynamicConditions->put($condition->getName(), $condition);
            } catch (Exception $e) {
                $this->handleDynamicConditionFailure('restore', null, $e, [
                    'name' => $name,
                    'rule_factory_key' => $ruleFactoryKey,
                ]);

                continue;
            }
        }

        // Mark as dirty - evaluation will happen when totals are requested
        $this->markDynamicConditionsDirty();

        return $this;
    }

    /**
     * Clear all dynamic conditions and their metadata.
     */
    public function clearDynamicConditions(): static
    {
        // Clear in-memory collection
        if (isset($this->dynamicConditions)) {
            $this->dynamicConditions = new CartConditionCollection;
        }

        // Clear metadata storage
        $this->storage->putMetadata(
            $this->getIdentifier(),
            $this->instance(),
            'dynamic_conditions',
            []
        );

        return $this;
    }

    /**
     * Get persisted dynamic condition metadata.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDynamicConditionMetadata(): array
    {
        $metadata = $this->storage->getMetadata(
            $this->getIdentifier(),
            $this->instance(),
            'dynamic_conditions'
        );

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * Evaluate a mixed array of factory keys and direct closures.
     *
     * @param  array<string|callable>  $rules  Mixed array of factory keys and closures
     * @param  string|array<string>|null  $ruleFactoryKey  Reference to store factory keys for persistence
     *
     * @param-out list<string>|null  $ruleFactoryKey
     *
     * @param  array<mixed>  $metadata  Additional metadata for rule evaluation
     * @return array<callable>
     */
    protected function evaluateMixedRules(array $rules, string | array | null &$ruleFactoryKey, array $metadata = []): array
    {
        if ($this->rulesFactory === null) {
            throw new InvalidArgumentException(
                'Cannot use factory keys without setting a RulesFactory. Call setRulesFactory() first.'
            );
        }

        $evaluatedRules = [];
        $factoryKeys = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                // Factory key → evaluate through factory
                if (! $this->rulesFactory->canCreateRules($rule)) {
                    throw new InvalidArgumentException("Unknown factory key: {$rule}");
                }

                $factoryRules = $this->rulesFactory->createRules($rule, $metadata);
                $evaluatedRules = array_merge($evaluatedRules, $factoryRules);
                $factoryKeys[] = $rule; // Track for persistence
            } elseif (is_callable($rule)) {
                // Direct closure → use as-is
                $evaluatedRules[] = $rule;
            } else {
                throw new InvalidArgumentException('Mixed rules must be strings (factory keys) or callables');
            }
        }

        // Store only the factory keys for persistence (closures are lost on restoration)
        $ruleFactoryKey = ! empty($factoryKeys) ? $factoryKeys : null;

        return $evaluatedRules;
    }

    /**
     * Restore rules from factory key(s).
     *
     * @param  string|array<string>  $ruleFactoryKey  Single key or array of keys
     * @param  array<string, mixed>  $metadata  Condition metadata
     * @return array<callable>
     */
    protected function restoreRulesFromFactoryKey(string | array $ruleFactoryKey, array $metadata): array
    {
        // Single factory key
        if (is_string($ruleFactoryKey)) {
            if (! $this->rulesFactory->canCreateRules($ruleFactoryKey)) {
                throw new InvalidArgumentException("Cannot restore: Unknown factory key '{$ruleFactoryKey}'");
            }

            return $this->rulesFactory->createRules($ruleFactoryKey, $metadata);
        }

        // Array of factory keys
        $combinedRules = [];
        foreach ($ruleFactoryKey as $key) {
            if (! $this->rulesFactory->canCreateRules($key)) {
                throw new InvalidArgumentException("Cannot restore: Unknown factory key '{$key}'");
            }

            $rules = $this->rulesFactory->createRules($key, $metadata);
            $combinedRules = array_merge($combinedRules, $rules);
        }

        return $combinedRules;
    }

    /**
     * Smart helper to create condition from array data with intelligent rule handling.
     *
     * @param  array<string, mixed>  $data  Condition data (requires `target_definition`)
     * @param  array<callable>|string|array<string>|Closure|null  $rules  Rules in any format
     * @param  string|array<string>|null  $ruleFactoryKey  Optional factory key(s)
     *
     * @param-out string|array<string>|null  $ruleFactoryKey
     *
     * @param  array<mixed>  $metadata  Additional metadata for rule evaluation
     */
    protected function createConditionFromArray(
        array $data,
        array | string | Closure | null $rules,
        string | array | null &$ruleFactoryKey,
        array $metadata = []
    ): CartCondition {
        // Smart rule evaluation (Filament-style)
        $evaluatedRules = $this->evaluateRules($rules, $ruleFactoryKey, $metadata);

        if (! isset($data['target_definition']) && isset($data['target'])) {
            $data['target_definition'] = ConditionTarget::from($data['target'])->toArray();
        }

        $target = $data['target_definition'] ?? null;

        if ($target === null) {
            throw new InvalidArgumentException('Condition target_definition is required.');
        }

        return new CartCondition(
            name: $data['name'] ?? throw new InvalidArgumentException('Condition name is required'),
            type: $data['type'] ?? 'percentage',
            target: $target,
            value: $data['value'] ?? 0,
            attributes: $data['attributes'] ?? [],
            order: $data['order'] ?? 0,
            rules: $evaluatedRules
        );
    }

    /**
     * Evaluate rules intelligently based on input type (like Filament's evaluate()).
     *
     * @param  array<callable>|string|array<string>|Closure|null  $rules  Rules in various formats
     * @param  string|array<string>|null  $ruleFactoryKey  Will be set if string/array key is provided
     *
     * @param-out string|array<string>|null  $ruleFactoryKey
     *
     * @param  array<mixed>  $metadata  Additional metadata for rule evaluation
     * @return array<callable> Evaluated rules
     */
    protected function evaluateRules(
        array | string | Closure | null $rules,
        string | array | null &$ruleFactoryKey,
        array $metadata = []
    ): array {
        $factoryMetadata = array_key_exists('context', $metadata)
            ? $metadata
            : ['context' => $metadata];
        // Case 1: Already an array → check contents
        if (is_array($rules)) {
            // Check if ALL elements are strings (factory keys only)
            $allStrings = ! empty($rules) && array_reduce(
                $rules,
                fn ($carry, $item) => $carry && is_string($item),
                true
            );

            if ($allStrings) {
                return $this->evaluateFactoryKeyArray($rules, $ruleFactoryKey, $factoryMetadata);
            }

            // Check for mixed array (strings + callables)
            $hasStrings = ! empty(array_filter($rules, fn ($item) => is_string($item)));

            if ($hasStrings) {
                return $this->evaluateMixedRules($rules, $ruleFactoryKey, $factoryMetadata);
            }

            // Otherwise, pure array of callables
            return $rules;
        }

        // Case 2: String → treat as factory key
        if (is_string($rules)) {
            return $this->evaluateFactoryKey($rules, $ruleFactoryKey, $factoryMetadata);
        }

        // Case 3: Closure → evaluate it (might return rules or need serialization)
        if ($rules instanceof Closure) {
            $result = $rules();

            // If closure returns an array, treat as rules
            if (is_array($result)) {
                return $result;
            }

            // Otherwise, wrap the closure itself as a rule
            return [$rules];
        }

        throw new InvalidArgumentException('Rules must be an array, string factory key, array of factory keys, or Closure');
    }

    /**
     * Evaluate a single factory key.
     *
     * @param  string  $factoryKey  The factory key
     * @param  string|array<string>|null  $ruleFactoryKey  Reference to store key for persistence
     *
     * @param-out string  $ruleFactoryKey
     *
     * @param  array<mixed>  $metadata  Additional metadata for rule evaluation
     * @return array<callable>
     */
    protected function evaluateFactoryKey(string $factoryKey, string | array | null &$ruleFactoryKey, array $metadata = []): array
    {
        if ($this->rulesFactory === null) {
            throw new InvalidArgumentException(
                'Cannot use factory key without setting a RulesFactory. Call setRulesFactory() first.'
            );
        }

        if (! $this->rulesFactory->canCreateRules($factoryKey)) {
            throw new InvalidArgumentException("Unknown factory key: {$factoryKey}");
        }

        $ruleFactoryKey = $factoryKey; // Set for persistence

        return $this->rulesFactory->createRules($factoryKey, $metadata);
    }

    /**
     * Evaluate an array of factory keys and combine their rules.
     *
     * @param  array<string>  $factoryKeys  Array of factory keys
     * @param  string|array<string>|null  $ruleFactoryKey  Reference to store keys for persistence
     *
     * @param-out array<string>  $ruleFactoryKey
     *
     * @param  array<mixed>  $metadata  Additional metadata for rule evaluation
     * @return array<callable>
     */
    protected function evaluateFactoryKeyArray(array $factoryKeys, string | array | null &$ruleFactoryKey, array $metadata = []): array
    {
        if ($this->rulesFactory === null) {
            throw new InvalidArgumentException(
                'Cannot use factory keys without setting a RulesFactory. Call setRulesFactory() first.'
            );
        }

        $combinedRules = [];

        foreach ($factoryKeys as $key) {
            if (! $this->rulesFactory->canCreateRules($key)) {
                throw new InvalidArgumentException("Unknown factory key: {$key}");
            }

            // Merge rules from this factory key
            $rules = $this->rulesFactory->createRules($key, $metadata);
            $combinedRules = array_merge($combinedRules, $rules);
        }

        $ruleFactoryKey = $factoryKeys; // Store array for persistence

        return $combinedRules;
    }

    /**
     * Initialize the dynamic conditions collection if not already initialized.
     */
    protected function initializeDynamicConditions(): void
    {
        if (! isset($this->dynamicConditions)) {
            $this->dynamicConditions = new CartConditionCollection;
        }
    }

    /**
     * Persist dynamic condition metadata for later restoration.
     *
     * @param  CartCondition  $condition  The condition to persist
     * @param  string|array<string>  $ruleFactoryKey  Key(s) for rule recreation
     * @param  array<string, mixed>  $context  Additional metadata context for factories
     */
    protected function persistDynamicConditionMetadata(CartCondition $condition, string | array $ruleFactoryKey, array $context = []): void
    {
        $existingMetadata = $this->getDynamicConditionMetadata();

        $existingMetadata[$condition->getName()] = [
            'type' => $condition->getType(),
            'target_scope' => $condition->getTargetDefinition()->scope->value,
            'target_definition' => $condition->getTargetDefinition()->toArray(),
            'value' => $condition->getValue(),
            'attributes' => $condition->getAttributes(),
            'order' => $condition->getOrder(),
            'rule_factory_key' => $ruleFactoryKey, // Can be string or array
            'created_at' => time(),
            'context' => $context,
        ];

        $this->storage->putMetadata(
            $this->getIdentifier(),
            $this->instance(),
            'dynamic_conditions',
            $existingMetadata
        );
    }

    /**
     * Invoke the registered failure handler, if any.
     *
     * @param  array<string, mixed>  $context
     */
    protected function handleDynamicConditionFailure(
        string $operation,
        ?CartCondition $condition,
        ?Throwable $exception = null,
        array $context = []
    ): void {
        if ($this->dynamicConditionFailureHandler === null) {
            return;
        }

        ($this->dynamicConditionFailureHandler)($operation, $condition, $exception, $context);
    }

    /**
     * Remove dynamic condition metadata from storage.
     *
     * @param  string  $name  Name of the condition to remove
     */
    protected function removeDynamicConditionMetadata(string $name): void
    {
        $metadata = $this->getDynamicConditionMetadata();

        if (isset($metadata[$name])) {
            unset($metadata[$name]);

            $this->storage->putMetadata(
                $this->getIdentifier(),
                $this->instance(),
                'dynamic_conditions',
                $metadata
            );
        }
    }

    /**
     * Handle dynamic conditions targeting cart-level scopes.
     */
    private function evaluateCartScopedDynamicCondition(CartCondition $condition): void
    {
        try {
            $shouldApply = $condition->shouldApply($this);
        } catch (Throwable $exception) {
            $this->handleDynamicConditionFailure('evaluate', $condition, $exception, [
                'scope' => $condition->getTargetDefinition()->scope->value,
            ]);

            $this->removeCondition($condition->getName());

            return;
        }

        if ($shouldApply) {
            // Use getConditionsFromStorage() to avoid recursion - getConditions() triggers evaluation
            if (! $this->getConditionsFromStorage()->has($condition->getName())) {
                $staticCondition = $condition->withoutRules();
                $this->addCondition($staticCondition);
            }

            return;
        }

        $this->removeCondition($condition->getName());
    }

    /**
     * Handle dynamic conditions targeting item-level scopes.
     */
    private function evaluateItemScopedDynamicCondition(CartCondition $condition): void
    {
        // Use getItemsFromStorage() to avoid infinite recursion since getItems()
        // triggers evaluateDynamicConditionsIfDirty() which calls this method
        foreach ($this->getItemsFromStorage() as $item) {
            try {
                $shouldApply = $condition->shouldApply($this, $item);
            } catch (Throwable $exception) {
                $this->handleDynamicConditionFailure('evaluate', $condition, $exception, [
                    'scope' => ConditionScope::ITEMS->value,
                    'item_id' => $item->id,
                ]);
                $this->removeItemCondition($item->id, $condition->getName());

                continue;
            }

            if ($shouldApply) {
                if (! $item->conditions->has($condition->getName())) {
                    $staticCondition = $condition->withoutRules();
                    $this->addItemCondition($item->id, $staticCondition);
                }

                continue;
            }

            $this->removeItemCondition($item->id, $condition->getName());
        }
    }

    /**
     * @param  array<string, mixed>  $conditionData
     * @return ConditionTarget|string|array<string, mixed>
     */
    private function resolveDynamicConditionTarget(array $conditionData): ConditionTarget | string | array
    {
        if (isset($conditionData['target_definition']) && is_array($conditionData['target_definition'])) {
            return ConditionTarget::from($conditionData['target_definition']);
        }

        throw new InvalidCartConditionException('Dynamic condition metadata missing target definition.');
    }
}
