<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Conditions\TargetPresets;
use Throwable;

/**
 * Provides friendly labels and helpers for common condition targeting presets.
 */
enum ConditionTargetPreset: string
{
    case CartSubtotal = 'cart_subtotal';
    case GrandTotal = 'grand_total';
    case Shipments = 'shipments';
    case Taxable = 'taxable';
    case Items = 'items';
    case Custom = 'custom';

    public static function default(): self
    {
        return self::CartSubtotal;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label() . ' – ' . $case->description();
        }

        return $options;
    }

    public static function detect(?string $dsl): ?self
    {
        if ($dsl === null || $dsl === '') {
            return null;
        }

        try {
            $normalized = ConditionTarget::from($dsl)->toDsl();
        } catch (Throwable) {
            return null;
        }

        foreach (self::cases() as $preset) {
            if ($preset === self::Custom) {
                continue;
            }

            if ($preset->dsl() === $normalized) {
                return $preset;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::CartSubtotal => 'Cart subtotal',
            self::GrandTotal => 'Cart grand total',
            self::Shipments => 'Shipments / shipping',
            self::Taxable => 'Taxable amount',
            self::Items => 'Each cart item',
            self::Custom => 'Custom target',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CartSubtotal => 'Applies before shipping, tax, and payments (aggregate)',
            self::GrandTotal => 'Applies after shipping/tax/payment adjustments (aggregate)',
            self::Shipments => 'Applies to each shipment/shipping group',
            self::Taxable => 'Applies to pre-tax amount (before tax calculation)',
            self::Items => 'Applies to every line item individually',
            self::Custom => 'Provide a custom DSL expression',
        };
    }

    public function target(): ?ConditionTarget
    {
        return match ($this) {
            self::CartSubtotal => TargetPresets::cartSubtotal(),
            self::GrandTotal => TargetPresets::cartGrandTotal(),
            self::Shipments => TargetPresets::cartShipping(),
            self::Taxable => TargetPresets::cartTaxable(),
            self::Items => TargetPresets::itemsPerItem(),
            self::Custom => null,
        };
    }

    public function dsl(): ?string
    {
        return $this->target()?->toDsl();
    }
}
