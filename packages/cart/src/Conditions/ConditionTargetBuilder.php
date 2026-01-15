<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionFilterOperator;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;

final class ConditionTargetBuilder
{
    /** @var list<ConditionFilter> */
    private array $filters = [];

    private ?ConditionGrouping $grouping = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    public function __construct(
        private ConditionScope $scope,
        private ConditionPhase $phase,
        private ConditionApplication $application
    ) {}

    public function phase(ConditionPhase $phase): self
    {
        $this->phase = $phase;

        return $this;
    }

    public function apply(ConditionApplication $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function applyPerItem(): self
    {
        return $this->apply(ConditionApplication::PER_ITEM);
    }

    public function applyAggregate(): self
    {
        return $this->apply(ConditionApplication::AGGREGATE);
    }

    public function applyPerUnit(): self
    {
        return $this->apply(ConditionApplication::PER_UNIT);
    }

    public function applyPerGroup(): self
    {
        return $this->apply(ConditionApplication::PER_GROUP);
    }

    public function where(string $field, ConditionFilterOperator | string $operator, mixed $value): self
    {
        $operator = $operator instanceof ConditionFilterOperator
            ? $operator
            : ConditionFilterOperator::fromString($operator);

        $this->filters[] = new ConditionFilter($field, $operator, $value);

        return $this;
    }

    public function whereAttribute(string $attribute, ConditionFilterOperator | string $operator, mixed $value): self
    {
        return $this->where('attributes.' . $attribute, $operator, $value);
    }

    public function groupBy(?string $field, ?string $weightField = null, ?int $limit = null): self
    {
        if ($field === null) {
            $this->grouping = null;
        } else {
            $this->grouping = new ConditionGrouping($field, $weightField, $limit);
        }

        return $this;
    }

    public function groupingPreset(string $preset): self
    {
        $this->grouping = ConditionGrouping::forPreset($preset);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function build(): ConditionTarget
    {
        $selector = new ConditionSelector($this->filters, $this->grouping);

        return new ConditionTarget(
            $this->scope,
            $this->phase,
            $this->application,
            $selector->isEmpty() ? null : $selector,
            $this->meta
        );
    }
}
