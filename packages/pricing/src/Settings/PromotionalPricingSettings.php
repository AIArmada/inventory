<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Settings for promotional pricing features.
 */
class PromotionalPricingSettings extends Settings
{
    /**
     * Whether flash sales are enabled.
     */
    public bool $flashSalesEnabled;

    /**
     * Default flash sale duration in hours.
     */
    public int $defaultFlashSaleDurationHours;

    /**
     * Maximum discount percentage allowed.
     */
    public int $maxDiscountPercentage;

    /**
     * Whether to allow stacking of promotions.
     */
    public bool $allowPromotionStacking;

    /**
     * Maximum number of stackable promotions.
     */
    public int $maxStackablePromotions;

    /**
     * Whether to show original price alongside sale price.
     */
    public bool $showOriginalPrice;

    /**
     * Whether to show countdown timers for time-limited offers.
     */
    public bool $showCountdownTimers;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'pricing_promotional';
    }
}
