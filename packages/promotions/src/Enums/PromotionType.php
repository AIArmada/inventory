<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PromotionType: string implements HasColor, HasIcon, HasLabel
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case BuyXGetY = 'buy_x_get_y';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Off',
            self::Fixed => 'Fixed Amount',
            self::BuyXGetY => 'Buy X Get Y',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function icon(): string
    {
        return match ($this) {
            self::Percentage => 'heroicon-o-receipt-percent',
            self::Fixed => 'heroicon-o-currency-dollar',
            self::BuyXGetY => 'heroicon-o-gift',
        };
    }

    public function getIcon(): ?string
    {
        return $this->icon();
    }

    public function color(): string
    {
        return match ($this) {
            self::Percentage => 'success',
            self::Fixed => 'primary',
            self::BuyXGetY => 'warning',
        };
    }

    public function getColor(): string | array | null
    {
        return $this->color();
    }

    public function formatValue(int $value): string
    {
        return match ($this) {
            self::Percentage => "{$value}%",
            self::Fixed => '$' . number_format($value / 100, 2),
            self::BuyXGetY => "Buy X Get {$value}",
        };
    }
}
