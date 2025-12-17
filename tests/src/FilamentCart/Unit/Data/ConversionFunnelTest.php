<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\ConversionFunnel;

describe('ConversionFunnel', function (): void {
    it('can be created with constructor', function (): void {
        $funnel = new ConversionFunnel(
            carts_created: 100,
            items_added: 80,
            checkout_started: 40,
            checkout_completed: 20,
            drop_off_rates: ['created_to_items' => 0.2],
        );

        expect($funnel->carts_created)->toBe(100);
        expect($funnel->items_added)->toBe(80);
        expect($funnel->checkout_started)->toBe(40);
        expect($funnel->checkout_completed)->toBe(20);
    });

    it('can be calculated via factory method', function (): void {
        $funnel = ConversionFunnel::calculate(
            cartsCreated: 100,
            itemsAdded: 80,
            checkoutStarted: 40,
            checkoutCompleted: 20,
        );

        expect($funnel->carts_created)->toBe(100);
        expect($funnel->items_added)->toBe(80);
        expect($funnel->checkout_started)->toBe(40);
        expect($funnel->checkout_completed)->toBe(20);
    });

    it('calculates drop-off rates correctly', function (): void {
        $funnel = ConversionFunnel::calculate(
            cartsCreated: 100,
            itemsAdded: 80,
            checkoutStarted: 40,
            checkoutCompleted: 20,
        );

        // 20% drop off from created to items (100 -> 80) - use toBeCloseTo for float comparison
        expect($funnel->drop_off_rates['created_to_items'])->toBeGreaterThanOrEqual(0.19);
        expect($funnel->drop_off_rates['created_to_items'])->toBeLessThanOrEqual(0.21);
        // 50% drop off from items to checkout (80 -> 40)
        expect($funnel->drop_off_rates['items_to_checkout'])->toBe(0.5);
        // 50% drop off from checkout start to complete (40 -> 20)
        expect($funnel->drop_off_rates['checkout_to_complete'])->toBe(0.5);
    });

    it('handles zero carts created gracefully', function (): void {
        $funnel = ConversionFunnel::calculate(
            cartsCreated: 0,
            itemsAdded: 0,
            checkoutStarted: 0,
            checkoutCompleted: 0,
        );

        expect($funnel->drop_off_rates)->toBeEmpty();
        expect($funnel->getOverallDropOffRate())->toBe(0.0);
        expect($funnel->getConversionRate())->toBe(0.0);
    });

    it('handles partial funnel data', function (): void {
        $funnel = ConversionFunnel::calculate(
            cartsCreated: 100,
            itemsAdded: 0,
            checkoutStarted: 0,
            checkoutCompleted: 0,
        );

        expect($funnel->drop_off_rates)->toHaveKey('created_to_items');
        // 100% drop off = 1
        expect($funnel->drop_off_rates['created_to_items'])->toBe(1);
    });

    it('calculates overall drop-off rate', function (): void {
        $funnel = ConversionFunnel::calculate(
            cartsCreated: 100,
            itemsAdded: 80,
            checkoutStarted: 40,
            checkoutCompleted: 20,
        );

        // 20 out of 100 completed = 80% dropped off
        expect($funnel->getOverallDropOffRate())->toBe(0.8);
    });

    it('calculates conversion rate', function (): void {
        $funnel = ConversionFunnel::calculate(
            cartsCreated: 100,
            itemsAdded: 80,
            checkoutStarted: 40,
            checkoutCompleted: 20,
        );

        // 20 out of 100 completed = 20% conversion
        expect($funnel->getConversionRate())->toBe(0.2);
    });

    it('conversion rate and drop-off rate sum to 1', function (): void {
        $funnel = ConversionFunnel::calculate(
            cartsCreated: 100,
            itemsAdded: 80,
            checkoutStarted: 40,
            checkoutCompleted: 25,
        );

        $sum = $funnel->getConversionRate() + $funnel->getOverallDropOffRate();
        expect($sum)->toBe(1.0);
    });
});
