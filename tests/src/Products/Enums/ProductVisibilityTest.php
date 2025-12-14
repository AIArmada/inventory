<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductVisibility;

describe('ProductVisibility Enum', function (): void {
    describe('Values', function (): void {
        it('has all expected values', function (): void {
            expect(ProductVisibility::Catalog->value)->toBe('catalog')
                ->and(ProductVisibility::Search->value)->toBe('search')
                ->and(ProductVisibility::CatalogSearch->value)->toBe('catalog_search')
                ->and(ProductVisibility::Individual->value)->toBe('individual')
                ->and(ProductVisibility::Hidden->value)->toBe('hidden');
        });

        it('can be created from string', function (): void {
            expect(ProductVisibility::from('catalog'))->toBe(ProductVisibility::Catalog)
                ->and(ProductVisibility::from('search'))->toBe(ProductVisibility::Search)
                ->and(ProductVisibility::from('catalog_search'))->toBe(ProductVisibility::CatalogSearch)
                ->and(ProductVisibility::from('individual'))->toBe(ProductVisibility::Individual)
                ->and(ProductVisibility::from('hidden'))->toBe(ProductVisibility::Hidden);
        });
    });

    describe('label()', function (): void {
        it('returns translation key for catalog', function (): void {
            expect(ProductVisibility::Catalog->label())->not->toBeEmpty();
        });

        it('returns translation key for search', function (): void {
            expect(ProductVisibility::Search->label())->not->toBeEmpty();
        });

        it('returns translation key for catalog_search', function (): void {
            expect(ProductVisibility::CatalogSearch->label())->not->toBeEmpty();
        });

        it('returns translation key for individual', function (): void {
            expect(ProductVisibility::Individual->label())->not->toBeEmpty();
        });

        it('returns translation key for hidden', function (): void {
            expect(ProductVisibility::Hidden->label())->not->toBeEmpty();
        });
    });

    describe('inCatalog()', function (): void {
        it('returns true for catalog', function (): void {
            expect(ProductVisibility::Catalog->inCatalog())->toBeTrue();
        });

        it('returns true for catalog_search', function (): void {
            expect(ProductVisibility::CatalogSearch->inCatalog())->toBeTrue();
        });

        it('returns false for search', function (): void {
            expect(ProductVisibility::Search->inCatalog())->toBeFalse();
        });

        it('returns false for individual', function (): void {
            expect(ProductVisibility::Individual->inCatalog())->toBeFalse();
        });

        it('returns false for hidden', function (): void {
            expect(ProductVisibility::Hidden->inCatalog())->toBeFalse();
        });
    });

    describe('inSearch()', function (): void {
        it('returns true for search', function (): void {
            expect(ProductVisibility::Search->inSearch())->toBeTrue();
        });

        it('returns true for catalog_search', function (): void {
            expect(ProductVisibility::CatalogSearch->inSearch())->toBeTrue();
        });

        it('returns false for catalog', function (): void {
            expect(ProductVisibility::Catalog->inSearch())->toBeFalse();
        });

        it('returns false for individual', function (): void {
            expect(ProductVisibility::Individual->inSearch())->toBeFalse();
        });

        it('returns false for hidden', function (): void {
            expect(ProductVisibility::Hidden->inSearch())->toBeFalse();
        });
    });

    describe('isDirectlyAccessible()', function (): void {
        it('returns true for catalog', function (): void {
            expect(ProductVisibility::Catalog->isDirectlyAccessible())->toBeTrue();
        });

        it('returns true for search', function (): void {
            expect(ProductVisibility::Search->isDirectlyAccessible())->toBeTrue();
        });

        it('returns true for catalog_search', function (): void {
            expect(ProductVisibility::CatalogSearch->isDirectlyAccessible())->toBeTrue();
        });

        it('returns true for individual', function (): void {
            expect(ProductVisibility::Individual->isDirectlyAccessible())->toBeTrue();
        });

        it('returns false for hidden', function (): void {
            expect(ProductVisibility::Hidden->isDirectlyAccessible())->toBeFalse();
        });
    });
});
