<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

use AIArmada\Inventory\Models\InventorySerial;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @method InventorySerial getModel()
 */
abstract class SerialStatus extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    public function isAllocatable(): bool
    {
        return false;
    }

    public function isInStock(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?Model $model = null): array
    {
        $model ??= new InventorySerial;

        $options = [];

        /** @var class-string<SerialStatus> $stateClass */
        foreach (self::all()->all() as $stateClass) {
            $state = new $stateClass($model);
            $options[$state->getValue()] = $state->label();
        }

        return $options;
    }

    /**
     * @return array<int, class-string<SerialStatus>>
     */
    public static function classes(): array
    {
        return self::all()->values()->all();
    }

    public static function normalize(string | SerialStatus $status): string
    {
        if ($status instanceof SerialStatus) {
            return $status->getValue();
        }

        if (is_string($status) && class_exists($status) && is_subclass_of($status, SerialStatus::class)) {
            return $status::getMorphClass();
        }

        return $status;
    }

    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(Available::class)
            ->allowTransition(Available::class, Reserved::class)
            ->allowTransition(Available::class, Sold::class)
            ->allowTransition(Available::class, InRepair::class)
            ->allowTransition(Available::class, Disposed::class)
            ->allowTransition(Available::class, Lost::class)
            ->allowTransition(Reserved::class, Available::class)
            ->allowTransition(Reserved::class, Sold::class)
            ->allowTransition(Reserved::class, Shipped::class)
            ->allowTransition(Sold::class, Shipped::class)
            ->allowTransition(Sold::class, Returned::class)
            ->allowTransition(Shipped::class, Returned::class)
            ->allowTransition(Returned::class, Available::class)
            ->allowTransition(Returned::class, InRepair::class)
            ->allowTransition(Returned::class, Disposed::class)
            ->allowTransition(InRepair::class, Available::class)
            ->allowTransition(InRepair::class, Disposed::class)
            ->allowTransition(Lost::class, Available::class)
            ->allowTransition(Recalled::class, Disposed::class)
            ->allowTransition(Recalled::class, Available::class);
    }
}
