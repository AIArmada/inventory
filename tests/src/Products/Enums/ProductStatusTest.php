<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;

describe('ProductStatus Enum', function (): void {
    describe('Values', function (): void {
        it('has all expected values', function (): void {
            expect(ProductStatus::Draft->value)->toBe('draft')
                ->and(ProductStatus::Active->value)->toBe('active')
                ->and(ProductStatus::Disabled->value)->toBe('disabled')
                ->and(ProductStatus::Archived->value)->toBe('archived');
        });

        it('can be created from string', function (): void {
            expect(ProductStatus::from('draft'))->toBe(ProductStatus::Draft)
                ->and(ProductStatus::from('active'))->toBe(ProductStatus::Active)
                ->and(ProductStatus::from('disabled'))->toBe(ProductStatus::Disabled)
                ->and(ProductStatus::from('archived'))->toBe(ProductStatus::Archived);
        });
    });

    describe('label()', function (): void {
        it('returns translation key for draft', function (): void {
            expect(ProductStatus::Draft->label())->not->toBeEmpty();
        });

        it('returns translation key for active', function (): void {
            expect(ProductStatus::Active->label())->not->toBeEmpty();
        });

        it('returns translation key for disabled', function (): void {
            expect(ProductStatus::Disabled->label())->not->toBeEmpty();
        });

        it('returns translation key for archived', function (): void {
            expect(ProductStatus::Archived->label())->not->toBeEmpty();
        });
    });

    describe('color()', function (): void {
        it('returns gray for draft', function (): void {
            expect(ProductStatus::Draft->color())->toBe('gray');
        });

        it('returns success for active', function (): void {
            expect(ProductStatus::Active->color())->toBe('success');
        });

        it('returns warning for disabled', function (): void {
            expect(ProductStatus::Disabled->color())->toBe('warning');
        });

        it('returns danger for archived', function (): void {
            expect(ProductStatus::Archived->color())->toBe('danger');
        });
    });

    describe('icon()', function (): void {
        it('returns correct icon for draft', function (): void {
            expect(ProductStatus::Draft->icon())->toBe('heroicon-o-pencil');
        });

        it('returns correct icon for active', function (): void {
            expect(ProductStatus::Active->icon())->toBe('heroicon-o-check-circle');
        });

        it('returns correct icon for disabled', function (): void {
            expect(ProductStatus::Disabled->icon())->toBe('heroicon-o-pause-circle');
        });

        it('returns correct icon for archived', function (): void {
            expect(ProductStatus::Archived->icon())->toBe('heroicon-o-archive-box');
        });
    });

    describe('isVisible()', function (): void {
        it('returns true for active', function (): void {
            expect(ProductStatus::Active->isVisible())->toBeTrue();
        });

        it('returns false for draft', function (): void {
            expect(ProductStatus::Draft->isVisible())->toBeFalse();
        });

        it('returns false for disabled', function (): void {
            expect(ProductStatus::Disabled->isVisible())->toBeFalse();
        });

        it('returns false for archived', function (): void {
            expect(ProductStatus::Archived->isVisible())->toBeFalse();
        });
    });

    describe('isPurchasable()', function (): void {
        it('returns true for active', function (): void {
            expect(ProductStatus::Active->isPurchasable())->toBeTrue();
        });

        it('returns false for draft', function (): void {
            expect(ProductStatus::Draft->isPurchasable())->toBeFalse();
        });

        it('returns false for disabled', function (): void {
            expect(ProductStatus::Disabled->isPurchasable())->toBeFalse();
        });

        it('returns false for archived', function (): void {
            expect(ProductStatus::Archived->isPurchasable())->toBeFalse();
        });
    });
});
