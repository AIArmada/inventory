<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * General pricing settings for the commerce platform.
 */
class PricingSettings extends Settings
{
    /**
     * Default currency code (ISO 4217).
     */
    public string $defaultCurrency;

    /**
     * Number of decimal places for price display.
     */
    public int $decimalPlaces;

    /**
     * Whether to show prices inclusive of tax.
     */
    public bool $pricesIncludeTax;

    /**
     * Rounding mode: 'up', 'down', 'half_up', 'half_down'.
     */
    public string $roundingMode;

    /**
     * Minimum order value in minor units (cents).
     */
    public int $minimumOrderValue;

    /**
     * Maximum order value in minor units (cents).
     */
    public int $maximumOrderValue;

    /**
     * Whether to enable promotional pricing.
     */
    public bool $promotionalPricingEnabled;

    /**
     * Whether to enable tiered pricing.
     */
    public bool $tieredPricingEnabled;

    /**
     * Whether to enable customer group pricing.
     */
    public bool $customerGroupPricingEnabled;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'pricing';
    }

    /**
     * Get the currency symbol for the default currency.
     */
    public function getCurrencySymbol(): string
    {
        return match ($this->defaultCurrency) {
            'MYR' => 'RM',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'SGD' => 'S$',
            default => $this->defaultCurrency . ' ',
        };
    }

    /**
     * Format an amount in minor units to display format.
     */
    public function formatAmount(int $amountInMinorUnits): string
    {
        $amount = $amountInMinorUnits / (10 ** $this->decimalPlaces);

        return $this->getCurrencySymbol() . number_format($amount, $this->decimalPlaces);
    }
}
