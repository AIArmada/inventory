<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductVisibility;

describe('ProductVisibility Enum', function (): void {
    describe('Cases', function (): void {
        it('has all expected cases', function (): void {
            expect(ProductVisibility::cases())->toHaveCount(5);
            expect(ProductVisibility::Catalog)->toBeInstanceOf(ProductVisibility::class);
            expect(ProductVisibility::Search)->toBeInstanceOf(ProductVisibility::class);
            expect(ProductVisibility::CatalogSearch)->toBeInstanceOf(ProductVisibility::class);
            expect(ProductVisibility::Individual)->toBeInstanceOf(ProductVisibility::class);
            expect(ProductVisibility::Hidden)->toBeInstanceOf(ProductVisibility::class);
        });
    });

    describe('Label Method', function (): void {
        it('returns correct labels for each visibility', function (): void {
            expect(ProductVisibility::Catalog->label())->toBe(__('products::enums.visibility.catalog'));
            expect(ProductVisibility::Search->label())->toBe(__('products::enums.visibility.search'));
            expect(ProductVisibility::CatalogSearch->label())->toBe(__('products::enums.visibility.catalog_search'));
            expect(ProductVisibility::Individual->label())->toBe(__('products::enums.visibility.individual'));
            expect(ProductVisibility::Hidden->label())->toBe(__('products::enums.visibility.hidden'));
        });
    });

    describe('In Catalog Method', function (): void {
        it('returns true for catalog visibilities', function (): void {
            expect(ProductVisibility::Catalog->inCatalog())->toBeTrue();
            expect(ProductVisibility::CatalogSearch->inCatalog())->toBeTrue();
        });

        it('returns false for non-catalog visibilities', function (): void {
            expect(ProductVisibility::Search->inCatalog())->toBeFalse();
            expect(ProductVisibility::Individual->inCatalog())->toBeFalse();
            expect(ProductVisibility::Hidden->inCatalog())->toBeFalse();
        });
    });

    describe('In Search Method', function (): void {
        it('returns true for search visibilities', function (): void {
            expect(ProductVisibility::Search->inSearch())->toBeTrue();
            expect(ProductVisibility::CatalogSearch->inSearch())->toBeTrue();
        });

        it('returns false for non-search visibilities', function (): void {
            expect(ProductVisibility::Catalog->inSearch())->toBeFalse();
            expect(ProductVisibility::Individual->inSearch())->toBeFalse();
            expect(ProductVisibility::Hidden->inSearch())->toBeFalse();
        });
    });

    describe('Is Directly Accessible Method', function (): void {
        it('returns true for all visibilities except hidden', function (): void {
            expect(ProductVisibility::Catalog->isDirectlyAccessible())->toBeTrue();
            expect(ProductVisibility::Search->isDirectlyAccessible())->toBeTrue();
            expect(ProductVisibility::CatalogSearch->isDirectlyAccessible())->toBeTrue();
            expect(ProductVisibility::Individual->isDirectlyAccessible())->toBeTrue();
        });

        it('returns false for hidden visibility', function (): void {
            expect(ProductVisibility::Hidden->isDirectlyAccessible())->toBeFalse();
        });
    });
});
