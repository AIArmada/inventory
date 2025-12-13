<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

use Spatie\LaravelData\Data;

/**
 * Conversion funnel DTO.
 */
class ConversionFunnel extends Data
{
    /**
     * @param  array<string, float>  $drop_off_rates
     */
    public function __construct(
        public int $carts_created,
        public int $items_added,
        public int $checkout_started,
        public int $checkout_completed,
        public array $drop_off_rates,
    ) {}

    public static function calculate(
        int $cartsCreated,
        int $itemsAdded,
        int $checkoutStarted,
        int $checkoutCompleted,
    ): self {
        $dropOffRates = [];

        if ($cartsCreated > 0) {
            $dropOffRates['created_to_items'] = 1 - ($itemsAdded / $cartsCreated);
        }

        if ($itemsAdded > 0) {
            $dropOffRates['items_to_checkout'] = 1 - ($checkoutStarted / $itemsAdded);
        }

        if ($checkoutStarted > 0) {
            $dropOffRates['checkout_to_complete'] = 1 - ($checkoutCompleted / $checkoutStarted);
        }

        return new self(
            carts_created: $cartsCreated,
            items_added: $itemsAdded,
            checkout_started: $checkoutStarted,
            checkout_completed: $checkoutCompleted,
            drop_off_rates: $dropOffRates,
        );
    }

    /**
     * Get the overall drop-off rate from carts created to checkout completed.
     */
    public function getOverallDropOffRate(): float
    {
        if ($this->carts_created === 0) {
            return 0.0;
        }

        return 1 - ($this->checkout_completed / $this->carts_created);
    }

    /**
     * Get the conversion rate from carts created to checkout completed.
     */
    public function getConversionRate(): float
    {
        if ($this->carts_created === 0) {
            return 0.0;
        }

        return $this->checkout_completed / $this->carts_created;
    }
}
