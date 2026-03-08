<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

use AIArmada\Inventory\Models\InventoryBackorder;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method InventoryBackorder getModel()
 */
abstract class BackorderStatus extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    public function isOpen(): bool
    {
        return false;
    }

    public function isClosed(): bool
    {
        return false;
    }

    public function canFulfill(): bool
    {
        return false;
    }

    public function canCancel(): bool
    {
        return false;
    }

    public static function normalize(string | BackorderStatus $status): string
    {
        if ($status instanceof BackorderStatus) {
            return $status->getValue();
        }

        if (class_exists($status) && is_subclass_of($status, BackorderStatus::class)) {
            return $status::getMorphClass();
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new InventoryBackorder;

        $options = [];

        /** @var class-string<BackorderStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    public static function labelFor(string | BackorderStatus $status, ?Model $model = null): string
    {
        if ($status instanceof BackorderStatus) {
            return $status->label();
        }

        $model ??= new InventoryBackorder;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->label();
    }

    public static function colorFor(string | BackorderStatus $status, ?Model $model = null): string
    {
        if ($status instanceof BackorderStatus) {
            return $status->color();
        }

        $model ??= new InventoryBackorder;
        $stateClass = self::resolveStateClassFor($status, $model);

        return (new $stateClass($model))->color();
    }

    public static function fromString(string | BackorderStatus $status, ?Model $model = null): BackorderStatus
    {
        if ($status instanceof BackorderStatus) {
            return $status;
        }

        $model ??= new InventoryBackorder;
        $stateClass = self::resolveStateClassFor($status, $model);

        return new $stateClass($model);
    }

    /**
     * @return class-string<BackorderStatus>
     */
    public static function resolveStateClassFor(string | BackorderStatus $status, ?Model $model = null): string
    {
        if ($status instanceof BackorderStatus) {
            return $status::class;
        }

        if (class_exists($status) && is_subclass_of($status, BackorderStatus::class)) {
            return $status;
        }

        $model ??= new InventoryBackorder;

        /** @var class-string<BackorderStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            if ($state->getValue() === $status) {
                return $stateClass;
            }
        }

        return Pending::class;
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, PartiallyFulfilled::class)
            ->allowTransition(Pending::class, Fulfilled::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Pending::class, Expired::class)
            ->allowTransition(PartiallyFulfilled::class, Fulfilled::class)
            ->allowTransition(PartiallyFulfilled::class, Cancelled::class)
            ->allowTransition(PartiallyFulfilled::class, Expired::class);
    }
}
