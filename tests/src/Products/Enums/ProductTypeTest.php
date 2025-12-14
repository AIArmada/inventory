<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductType;

describe('ProductType Enum', function (): void {
    describe('Values', function (): void {
        it('has all expected values', function (): void {
            expect(ProductType::Simple->value)->toBe('simple')
                ->and(ProductType::Configurable->value)->toBe('configurable')
                ->and(ProductType::Bundle->value)->toBe('bundle')
                ->and(ProductType::Digital->value)->toBe('digital')
                ->and(ProductType::Subscription->value)->toBe('subscription');
        });

        it('can be created from string', function (): void {
            expect(ProductType::from('simple'))->toBe(ProductType::Simple)
                ->and(ProductType::from('configurable'))->toBe(ProductType::Configurable)
                ->and(ProductType::from('bundle'))->toBe(ProductType::Bundle)
                ->and(ProductType::from('digital'))->toBe(ProductType::Digital)
                ->and(ProductType::from('subscription'))->toBe(ProductType::Subscription);
        });
    });

    describe('label()', function (): void {
        it('returns translation key for simple', function (): void {
            expect(ProductType::Simple->label())->not->toBeEmpty();
        });

        it('returns translation key for configurable', function (): void {
            expect(ProductType::Configurable->label())->not->toBeEmpty();
        });

        it('returns translation key for bundle', function (): void {
            expect(ProductType::Bundle->label())->not->toBeEmpty();
        });

        it('returns translation key for digital', function (): void {
            expect(ProductType::Digital->label())->not->toBeEmpty();
        });

        it('returns translation key for subscription', function (): void {
            expect(ProductType::Subscription->label())->not->toBeEmpty();
        });
    });

    describe('hasVariants()', function (): void {
        it('returns true for configurable', function (): void {
            expect(ProductType::Configurable->hasVariants())->toBeTrue();
        });

        it('returns false for simple', function (): void {
            expect(ProductType::Simple->hasVariants())->toBeFalse();
        });

        it('returns false for bundle', function (): void {
            expect(ProductType::Bundle->hasVariants())->toBeFalse();
        });

        it('returns false for digital', function (): void {
            expect(ProductType::Digital->hasVariants())->toBeFalse();
        });

        it('returns false for subscription', function (): void {
            expect(ProductType::Subscription->hasVariants())->toBeFalse();
        });
    });

    describe('isPhysical()', function (): void {
        it('returns true for simple', function (): void {
            expect(ProductType::Simple->isPhysical())->toBeTrue();
        });

        it('returns true for configurable', function (): void {
            expect(ProductType::Configurable->isPhysical())->toBeTrue();
        });

        it('returns true for bundle', function (): void {
            expect(ProductType::Bundle->isPhysical())->toBeTrue();
        });

        it('returns false for digital', function (): void {
            expect(ProductType::Digital->isPhysical())->toBeFalse();
        });

        it('returns false for subscription', function (): void {
            expect(ProductType::Subscription->isPhysical())->toBeFalse();
        });
    });

    describe('icon()', function (): void {
        it('returns correct icon for simple', function (): void {
            expect(ProductType::Simple->icon())->toBe('heroicon-o-cube');
        });

        it('returns correct icon for configurable', function (): void {
            expect(ProductType::Configurable->icon())->toBe('heroicon-o-squares-2x2');
        });

        it('returns correct icon for bundle', function (): void {
            expect(ProductType::Bundle->icon())->toBe('heroicon-o-rectangle-group');
        });

        it('returns correct icon for digital', function (): void {
            expect(ProductType::Digital->icon())->toBe('heroicon-o-cloud-arrow-down');
        });

        it('returns correct icon for subscription', function (): void {
            expect(ProductType::Subscription->icon())->toBe('heroicon-o-arrow-path');
        });
    });
});
