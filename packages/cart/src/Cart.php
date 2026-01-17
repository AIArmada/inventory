<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipeline;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineContext;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineResult;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Traits\CalculatesTotals;
use AIArmada\Cart\Traits\DispatchesEvents;
use AIArmada\Cart\Traits\HasLazyPipeline;
use AIArmada\Cart\Traits\ManagesBuyables;
use AIArmada\Cart\Traits\ManagesConditions;
use AIArmada\Cart\Traits\ManagesDynamicConditions;
use AIArmada\Cart\Traits\ManagesInstances;
use AIArmada\Cart\Traits\ManagesItems;
use AIArmada\Cart\Traits\ManagesMetadata;
use AIArmada\Cart\Traits\ManagesStorage;
use Illuminate\Contracts\Events\Dispatcher;

final class Cart
{
    use CalculatesTotals;
    use DispatchesEvents;
    use HasLazyPipeline;
    use ManagesBuyables;
    use ManagesConditions;
    use ManagesDynamicConditions;
    use ManagesInstances;
    use ManagesItems;
    use ManagesMetadata;
    use ManagesStorage;

    private CartConditionResolver $conditionResolver;

    private ?ConditionProviderRegistry $conditionProviderRegistry = null;

    public function __construct(
        private StorageInterface $storage,
        private string $identifier,
        private ?Dispatcher $events = null,
        private string $instanceName = 'default',
        private bool $eventsEnabled = true,
        ?CartConditionResolver $conditionResolver = null,
        ?ConditionProviderRegistry $conditionProviderRegistry = null,
    ) {
        $this->conditionResolver = $conditionResolver
            ?? (function_exists('app') ? app(CartConditionResolver::class) : new CartConditionResolver);

        if ($conditionProviderRegistry !== null) {
            $this->conditionProviderRegistry = $conditionProviderRegistry;
        } elseif (function_exists('app') && app()->bound(ConditionProviderRegistry::class)) {
            $this->conditionProviderRegistry = app(ConditionProviderRegistry::class);
        }
    }

    /**
     * Get the cart identifier.
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Set the cart identifier.
     *
     * This creates a new cart instance with the specified identifier.
     * Useful for switching between different user/session carts at runtime.
     */
    public function setIdentifier(string $identifier): static
    {
        if ($this->identifier === $identifier) {
            return $this;
        }

        return new static(
            $this->storage,
            $identifier,
            $this->events,
            $this->instanceName,
            $this->eventsEnabled,
            $this->conditionResolver
        );
    }

    public function getConditionResolver(): CartConditionResolver
    {
        return $this->conditionResolver;
    }

    public function getConditionProviderRegistry(): ?ConditionProviderRegistry
    {
        return $this->conditionProviderRegistry;
    }

    /**
     * Get stored cart conditions without syncing providers.
     */
    public function getStoredConditions(): CartConditionCollection
    {
        return $this->getConditionsFromStorage();
    }

    /**
     * Sync registered condition providers with stored cart conditions.
     */
    public function syncConditionProviders(): void
    {
        $registry = $this->getConditionProviderRegistry();

        if ($registry === null) {
            return;
        }

        $providers = $registry->all();

        if ($providers === []) {
            return;
        }

        $conditions = $this->getConditionsFromStorage();
        $original = $conditions->toArray();
        $providerKeys = $registry->providerKeys();

        foreach ($conditions as $condition) {
            $providerKey = $condition->getAttribute('__provider');

            if (is_string($providerKey) && $providerKey !== '' && ! in_array($providerKey, $providerKeys, true)) {
                $conditions->forget($condition->getName());
            }
        }

        foreach ($providers as $provider) {
            $providerKey = $provider::class;

            foreach ($conditions as $condition) {
                if ($condition->getAttribute('__provider') !== $providerKey) {
                    continue;
                }

                if (! $provider->validate($condition, $this)) {
                    $conditions->forget($condition->getName());
                }
            }

            foreach ($provider->getConditionsFor($this) as $condition) {
                if (! $provider->validate($condition, $this)) {
                    continue;
                }

                $condition = $this->withProviderAttribute($condition, $providerKey);
                $conditions->put($condition->getName(), $condition);
            }
        }

        $updated = $conditions->toArray();

        if ($original !== $updated) {
            $this->storage->putConditions($this->getIdentifier(), $this->instance(), $updated);
            $this->invalidatePipelineCache();
        }
    }

    private function withProviderAttribute(CartCondition $condition, string $providerKey): CartCondition
    {
        if ($condition->getAttribute('__provider') === $providerKey) {
            return $condition;
        }

        $attributes = $condition->getAttributes();
        $attributes['__provider'] = $providerKey;

        return new CartCondition(
            name: $condition->getName(),
            type: $condition->getType(),
            target: $condition->getTargetDefinition(),
            value: $condition->getValue(),
            attributes: $attributes,
            order: $condition->getOrder(),
            rules: $condition->getRules(),
        );
    }

    /**
     * Evaluate the condition pipeline for the current cart state.
     *
     * @param  callable(ConditionPipeline):void|null  $configure  Optional pipeline configuration callback
     */
    public function evaluateConditionPipeline(?callable $configure = null): ConditionPipelineResult
    {
        $pipeline = new ConditionPipeline;

        if ($configure !== null) {
            $configure($pipeline);
        }

        return $pipeline->process(ConditionPipelineContext::fromCart($this));
    }

    /**
     * Initialize cart with rules factory for dynamic condition persistence.
     *
     * This method sets up the rules factory and automatically restores
     * any previously persisted dynamic conditions.
     *
     * @param  RulesFactoryInterface  $factory  Factory to create rule closures
     */
    public function withRulesFactory(RulesFactoryInterface $factory): static
    {
        $this->setRulesFactory($factory);
        $this->restoreDynamicConditions();

        return $this;
    }

    /**
     * Get cart version for change tracking
     * Useful for detecting cart modifications and optimistic locking
     *
     * @return int|null Version number or null if not supported by storage driver
     */
    public function getVersion(): ?int
    {
        return $this->storage->getVersion($this->getIdentifier(), $this->instance());
    }

    /**
     * Get cart ID (primary key) from storage
     * Useful for linking carts to external systems like payment gateways, orders, etc.
     *
     * @return string|null Cart UUID or null if not supported by storage driver
     */
    public function getId(): ?string
    {
        return $this->storage->getId($this->getIdentifier(), $this->instance());
    }

    /**
     * Get cart creation timestamp
     * Returns when the cart was first created in storage.
     *
     * @return string|null ISO 8601 timestamp or null if not supported
     */
    public function getCreatedAt(): ?string
    {
        return $this->storage->getCreatedAt($this->getIdentifier(), $this->instance());
    }

    /**
     * Get cart last updated timestamp
     * Returns when the cart was last modified.
     *
     * @return string|null ISO 8601 timestamp or null if not supported
     */
    public function getUpdatedAt(): ?string
    {
        return $this->storage->getUpdatedAt($this->getIdentifier(), $this->instance());
    }
}
