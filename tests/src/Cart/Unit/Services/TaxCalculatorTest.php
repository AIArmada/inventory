<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Services\TaxCalculator;
use Akaunting\Money\Money;

describe('TaxCalculator', function (): void {
    describe('construction and configuration', function (): void {
        it('can be instantiated with default values', function (): void {
            $calculator = new TaxCalculator;

            expect($calculator)->toBeInstanceOf(TaxCalculator::class);
            expect($calculator->getDefaultRate())->toEqual(0);  // May return 0.0
            expect($calculator->getDefaultRegion())->toBeNull();
            expect($calculator->pricesIncludeTax())->toBeFalse();
        });

        it('can be instantiated with custom values', function (): void {
            $calculator = new TaxCalculator(
                defaultRate: 0.08,
                defaultRegion: 'MY',
                pricesIncludeTax: true,
            );

            expect($calculator->getDefaultRate())->toBe(0.08);
            expect($calculator->getDefaultRegion())->toBe('MY');
            expect($calculator->pricesIncludeTax())->toBeTrue();
        });

        it('can set and get region rates', function (): void {
            $calculator = new TaxCalculator;

            $calculator->setRegionRate('MY', 0.08);
            $calculator->setRegionRate('SG', 0.09);
            $calculator->setRegionRate('GB', 0.20);

            expect($calculator->getRegionRate('MY'))->toBe(0.08);
            expect($calculator->getRegionRate('SG'))->toBe(0.09);
            expect($calculator->getRegionRate('GB'))->toBe(0.20);
        });

        it('returns default rate for unknown region', function (): void {
            $calculator = new TaxCalculator(defaultRate: 0.05);

            expect($calculator->getRegionRate('UNKNOWN'))->toBe(0.05);
        });

        it('supports fluent rate configuration', function (): void {
            $calculator = (new TaxCalculator)
                ->setRegionRate('MY', 0.08)
                ->setRegionRate('SG', 0.09);

            expect($calculator->getRegionRate('MY'))->toBe(0.08);
            expect($calculator->getRegionRate('SG'))->toBe(0.09);
        });
    });

    describe('tax calculation - exclusive pricing', function (): void {
        it('calculates tax for money amount', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: false);
            $calculator->setRegionRate('MY', 0.08);

            $amount = Money::USD(10000); // $100.00
            $tax = $calculator->calculateTax($amount, 'MY');

            expect($tax->getAmount())->toBe(800); // $8.00
        });

        it('calculates tax using default rate when no region specified', function (): void {
            $calculator = new TaxCalculator(
                defaultRate: 0.10,
                pricesIncludeTax: false,
            );

            $amount = Money::USD(10000); // $100.00
            $tax = $calculator->calculateTax($amount);

            expect($tax->getAmount())->toBe(1000); // $10.00
        });

        it('calculates zero tax when rate is zero', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: false);
            $calculator->setRegionRate('US-OR', 0.0); // Oregon has no sales tax

            $amount = Money::USD(10000);
            $tax = $calculator->calculateTax($amount, 'US-OR');

            expect($tax->getAmount())->toEqual(0);
        });

        it('handles various tax rates correctly', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: false);

            $testCases = [
                ['region' => 'MY', 'rate' => 0.08, 'expected' => 800],    // 8% SST
                ['region' => 'SG', 'rate' => 0.09, 'expected' => 900],    // 9% GST
                ['region' => 'GB', 'rate' => 0.20, 'expected' => 2000],   // 20% VAT
                ['region' => 'AU', 'rate' => 0.10, 'expected' => 1000],   // 10% GST
                ['region' => 'US-CA', 'rate' => 0.0725, 'expected' => 725], // 7.25%
            ];

            foreach ($testCases as $case) {
                $calculator->setRegionRate($case['region'], $case['rate']);
                $amount = Money::USD(10000);
                $tax = $calculator->calculateTax($amount, $case['region']);

                expect($tax->getAmount())->toBe($case['expected']);
            }
        });
    });

    describe('tax calculation - inclusive pricing', function (): void {
        it('extracts tax from inclusive price', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: true);
            $calculator->setRegionRate('GB', 0.20);

            // £120 including 20% VAT = £100 net + £20 VAT
            $amount = Money::GBP(12000);
            $tax = $calculator->calculateTax($amount, 'GB');

            expect($tax->getAmount())->toBe(2000); // £20.00
        });

        it('handles different inclusive rates', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: true);

            $testCases = [
                // £110 including 10% = £100 net + £10 tax
                ['region' => 'AU', 'rate' => 0.10, 'gross' => 11000, 'expected' => 1000],
                // £108 including 8% = £100 net + £8 tax
                ['region' => 'MY', 'rate' => 0.08, 'gross' => 10800, 'expected' => 800],
            ];

            foreach ($testCases as $case) {
                $calculator->setRegionRate($case['region'], $case['rate']);
                $amount = Money::GBP($case['gross']);
                $tax = $calculator->calculateTax($amount, $case['region']);

                expect($tax->getAmount())->toBe($case['expected']);
            }
        });
    });

    describe('registerRate method', function (): void {
        it('registers rate with name and description', function (): void {
            $calculator = (new TaxCalculator)
                ->registerRate('MY-SST', 8.0, 'Sales & Service Tax');

            expect($calculator->getRegionRate('MY-SST'))->toBe(0.08);
        });

        it('stores rate info', function (): void {
            $calculator = (new TaxCalculator)
                ->registerRate('UK-VAT', 20.0, 'VAT', inclusive: true);

            $rateInfo = $calculator->getRate('UK-VAT');

            expect($rateInfo)->not->toBeNull();
            expect($rateInfo['rate'])->toBe(20.0);
            expect($rateInfo['name'])->toBe('VAT');
            expect($rateInfo['inclusive'])->toBeTrue();
        });
    });

    describe('withDefaults factory', function (): void {
        it('creates calculator with common tax presets', function (): void {
            $calculator = TaxCalculator::withDefaults();

            expect($calculator->getRegionRate('MY-SST'))->toBe(0.08);
            expect($calculator->getRegionRate('SG-GST'))->toBe(0.09);
            expect($calculator->getRegionRate('UK-VAT'))->toBe(0.20);
            expect($calculator->getRegionRate('AU-GST'))->toBe(0.10);
        });
    });

    describe('createTaxCondition', function (): void {
        it('creates a cart condition for tax', function (): void {
            $calculator = new TaxCalculator;

            $condition = $calculator->createTaxCondition('Test Tax', 8.0);

            expect($condition)->toBeInstanceOf(CartCondition::class);
            expect($condition->getName())->toBe('Test Tax');
            expect($condition->getType())->toBe('tax');
            expect($condition->getValue())->toBe('+8%');
        });

        it('creates zero-value condition for inclusive pricing', function (): void {
            $calculator = new TaxCalculator;

            $condition = $calculator->createTaxCondition('VAT', 20.0, inclusive: true);

            expect($condition->getValue())->toEqual(0);
            expect($condition->getAttribute('inclusive'))->toBeTrue();
        });

        it('includes rate and inclusive flag in attributes', function (): void {
            $calculator = new TaxCalculator;

            $condition = $calculator->createTaxCondition('GST', 10.0, inclusive: false);

            expect($condition->getAttribute('rate'))->toBe(10.0);
            expect($condition->getAttribute('inclusive'))->toBeFalse();
        });
    });

    describe('service container integration', function (): void {
        it('can be resolved from container', function (): void {
            $calculator = app(TaxCalculator::class);

            expect($calculator)->toBeInstanceOf(TaxCalculator::class);
        });

        it('can be resolved via alias', function (): void {
            $calculator = app('cart.tax');

            expect($calculator)->toBeInstanceOf(TaxCalculator::class);
        });
    });

    describe('edge cases', function (): void {
        it('handles zero amount', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: false);
            $calculator->setRegionRate('MY', 0.08);

            $amount = Money::USD(0);
            $tax = $calculator->calculateTax($amount, 'MY');

            expect($tax->getAmount())->toEqual(0);  // Use toEqual for type-flexible comparison
        });

        it('handles very small amounts', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: false);
            $calculator->setRegionRate('MY', 0.08);

            $amount = Money::USD(1); // 1 cent
            $tax = $calculator->calculateTax($amount, 'MY');

            expect($tax->getAmount())->toBeGreaterThanOrEqual(0);
        });

        it('handles very large amounts', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: false);
            $calculator->setRegionRate('MY', 0.08);

            $amount = Money::USD(100000000); // $1,000,000.00
            $tax = $calculator->calculateTax($amount, 'MY');

            expect($tax->getAmount())->toBe(8000000); // $80,000.00
        });

        it('preserves currency from input amount', function (): void {
            $calculator = new TaxCalculator(pricesIncludeTax: false);
            $calculator->setRegionRate('MY', 0.08);

            $amountMyr = Money::MYR(10000);
            $taxMyr = $calculator->calculateTax($amountMyr, 'MY');

            $amountGbp = Money::GBP(10000);
            $taxGbp = $calculator->calculateTax($amountGbp, 'MY');

            expect($taxMyr->getCurrency()->getCurrency())->toBe('MYR');
            expect($taxGbp->getCurrency()->getCurrency())->toBe('GBP');
        });
    });

    describe('applyToCart', function (): void {
        beforeEach(function (): void {
            $this->storage = new \AIArmada\Cart\Testing\InMemoryStorage;
            $this->cart = new \AIArmada\Cart\Cart($this->storage, 'test-user');
            $this->cart->add('item-1', 'Product', 10000, 2);
        });

        it('applies tax condition to cart', function (): void {
            $calculator = TaxCalculator::withDefaults();

            $condition = $calculator->applyToCart($this->cart, 'MY-SST');

            expect($condition)->toBeInstanceOf(CartCondition::class)
                ->and($condition->getName())->toBe('Sales & Service Tax (SST)');
        });

        it('returns null for non-existent rate', function (): void {
            $calculator = new TaxCalculator;

            $condition = $calculator->applyToCart($this->cart, 'NON-EXISTENT');

            expect($condition)->toBeNull();
        });

        it('uses custom condition name when provided', function (): void {
            $calculator = TaxCalculator::withDefaults();

            $condition = $calculator->applyToCart($this->cart, 'MY-SST', 'Custom SST');

            expect($condition->getName())->toBe('Custom SST');
        });
    });

    describe('calculateForDisplay', function (): void {
        beforeEach(function (): void {
            $this->storage = new \AIArmada\Cart\Testing\InMemoryStorage;
            $this->cart = new \AIArmada\Cart\Cart($this->storage, 'test-user');
            $this->cart->add('item-1', 'Product', 10000, 2); // Total: 20000
        });

        it('calculates tax for display with valid rate', function (): void {
            $calculator = TaxCalculator::withDefaults();

            $result = $calculator->calculateForDisplay($this->cart, 'MY-SST');

            expect($result)->toHaveKeys(['amount', 'rate', 'name'])
                ->and($result['rate'])->toBe(8.0)
                ->and($result['name'])->toBe('Sales & Service Tax (SST)')
                ->and($result['amount'])->toBe(1600); // 20000 * 0.08
        });

        it('returns zero for non-existent rate', function (): void {
            $calculator = new TaxCalculator;

            $result = $calculator->calculateForDisplay($this->cart, 'NON-EXISTENT');

            expect($result['amount'])->toBe(0)
                ->and($result['rate'])->toBe(0.0)
                ->and($result['name'])->toBe('No Tax');
        });
    });

    describe('applyWithCategories', function (): void {
        beforeEach(function (): void {
            $this->storage = new \AIArmada\Cart\Testing\InMemoryStorage;
            $this->cart = new \AIArmada\Cart\Cart($this->storage, 'test-user');
            $this->cart->add('item-1', 'Product', 10000, 1);
        });

        it('applies tax based on category rates', function (): void {
            $calculator = TaxCalculator::withDefaults();

            $condition = $calculator->applyWithCategories($this->cart, 'Category Tax', 8.0);

            expect($condition)->toBeInstanceOf(CartCondition::class)
                ->and($condition->getName())->toBe('Category Tax')
                ->and($condition->getType())->toBe('tax');
        });

        it('uses custom condition name', function (): void {
            $calculator = TaxCalculator::withDefaults();

            $condition = $calculator->applyWithCategories($this->cart, 'Custom Cat Tax');

            expect($condition->getName())->toBe('Custom Cat Tax');
        });
    });

    describe('registerCategoryRate', function (): void {
        it('registers category-specific rates', function (): void {
            $calculator = (new TaxCalculator)
                ->registerCategoryRate('food', 0.0)
                ->registerCategoryRate('digital', 8.0)
                ->registerCategoryRate('standard', 6.0);

            expect($calculator)->toBeInstanceOf(TaxCalculator::class);
        });

        it('supports fluent interface', function (): void {
            $result = (new TaxCalculator)
                ->registerCategoryRate('a', 1.0)
                ->registerCategoryRate('b', 2.0);

            expect($result)->toBeInstanceOf(TaxCalculator::class);
        });
    });

    describe('setDefaultCategory', function (): void {
        it('sets default tax category', function (): void {
            $calculator = (new TaxCalculator)
                ->setDefaultCategory('digital');

            expect($calculator)->toBeInstanceOf(TaxCalculator::class);
        });

        it('supports fluent interface', function (): void {
            $result = (new TaxCalculator)
                ->setDefaultCategory('standard')
                ->registerCategoryRate('standard', 8.0);

            expect($result)->toBeInstanceOf(TaxCalculator::class);
        });
    });
});
