<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;

describe('ProductStatus Enum', function (): void {
    describe('Cases', function (): void {
        it('has all expected cases', function (): void {
            expect(ProductStatus::cases())->toHaveCount(4);
            expect(ProductStatus::Draft)->toBeInstanceOf(ProductStatus::class);
            expect(ProductStatus::Active)->toBeInstanceOf(ProductStatus::class);
            expect(ProductStatus::Disabled)->toBeInstanceOf(ProductStatus::class);
            expect(ProductStatus::Archived)->toBeInstanceOf(ProductStatus::class);
        });
    });

    describe('Label Method', function (): void {
        it('returns correct labels for each status', function (): void {
            expect(ProductStatus::Draft->label())->toBe(__('products::enums.status.draft'));
            expect(ProductStatus::Active->label())->toBe(__('products::enums.status.active'));
            expect(ProductStatus::Disabled->label())->toBe(__('products::enums.status.disabled'));
            expect(ProductStatus::Archived->label())->toBe(__('products::enums.status.archived'));
        });
    });

    describe('Color Method', function (): void {
        it('returns correct colors for each status', function (): void {
            expect(ProductStatus::Draft->color())->toBe('gray');
            expect(ProductStatus::Active->color())->toBe('success');
            expect(ProductStatus::Disabled->color())->toBe('warning');
            expect(ProductStatus::Archived->color())->toBe('danger');
        });
    });

    describe('Icon Method', function (): void {
        it('returns correct icons for each status', function (): void {
            expect(ProductStatus::Draft->icon())->toBe('heroicon-o-pencil');
            expect(ProductStatus::Active->icon())->toBe('heroicon-o-check-circle');
            expect(ProductStatus::Disabled->icon())->toBe('heroicon-o-pause-circle');
            expect(ProductStatus::Archived->icon())->toBe('heroicon-o-archive-box');
        });
    });

    describe('Is Visible Method', function (): void {
        it('returns true only for active status', function (): void {
            expect(ProductStatus::Active->isVisible())->toBeTrue();
        });

        it('returns false for non-active statuses', function (): void {
            expect(ProductStatus::Draft->isVisible())->toBeFalse();
            expect(ProductStatus::Disabled->isVisible())->toBeFalse();
            expect(ProductStatus::Archived->isVisible())->toBeFalse();
        });
    });

    describe('Is Purchasable Method', function (): void {
        it('returns true only for active status', function (): void {
            expect(ProductStatus::Active->isPurchasable())->toBeTrue();
        });

        it('returns false for non-active statuses', function (): void {
            expect(ProductStatus::Draft->isPurchasable())->toBeFalse();
            expect(ProductStatus::Disabled->isPurchasable())->toBeFalse();
            expect(ProductStatus::Archived->isPurchasable())->toBeFalse();
        });
    });
});
