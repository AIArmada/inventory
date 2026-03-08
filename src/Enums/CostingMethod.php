<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum CostingMethod: string
{
    case Fifo = 'fifo';
    case Lifo = 'lifo';
    case WeightedAverage = 'weighted_average';
    case Standard = 'standard';
    case SpecificIdentification = 'specific_identification';

    /**
     * @return array<string, CostingMethod>
     */
    public static function perpetualMethods(): array
    {
        return [
            self::Fifo->value => self::Fifo,
            self::Lifo->value => self::Lifo,
            self::WeightedAverage->value => self::WeightedAverage,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Fifo => 'FIFO (First In, First Out)',
            self::Lifo => 'LIFO (Last In, First Out)',
            self::WeightedAverage => 'Weighted Average',
            self::Standard => 'Standard Cost',
            self::SpecificIdentification => 'Specific Identification',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Fifo => 'FIFO',
            self::Lifo => 'LIFO',
            self::WeightedAverage => 'Avg',
            self::Standard => 'Std',
            self::SpecificIdentification => 'Specific',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fifo => 'info',
            self::Lifo => 'primary',
            self::WeightedAverage => 'success',
            self::Standard => 'warning',
            self::SpecificIdentification => 'gray',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Fifo => 'Oldest inventory sold first. Good for perishables and rising prices.',
            self::Lifo => 'Newest inventory sold first. May reduce tax burden in rising prices.',
            self::WeightedAverage => 'Average cost of all units. Smooths out price fluctuations.',
            self::Standard => 'Predetermined fixed cost. Good for manufacturing and budgeting.',
            self::SpecificIdentification => 'Tracks actual cost per unit. Best for unique/high-value items.',
        };
    }

    public function isPerpetual(): bool
    {
        return in_array($this, [self::Fifo, self::Lifo, self::WeightedAverage], true);
    }

    public function requiresLayerTracking(): bool
    {
        return in_array($this, [self::Fifo, self::Lifo, self::SpecificIdentification], true);
    }
}
