<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\MoneyNormalizer;

describe('MoneyNormalizer::toCents', function (): void {
    it('handles null as zero', function (): void {
        expect(MoneyNormalizer::toCents(null))->toBe(0);
    });

    it('returns int values as-is (treated as cents)', function (): void {
        expect(MoneyNormalizer::toCents(1999))->toBe(1999);
        expect(MoneyNormalizer::toCents(0))->toBe(0);
        expect(MoneyNormalizer::toCents(100))->toBe(100);
    });

    it('converts float dollars to cents', function (): void {
        expect(MoneyNormalizer::toCents(19.99))->toBe(1999);
        expect(MoneyNormalizer::toCents(99.00))->toBe(9900);
        expect(MoneyNormalizer::toCents(0.01))->toBe(1);
        expect(MoneyNormalizer::toCents(0.99))->toBe(99);
    });

    it('handles float rounding correctly', function (): void {
        expect(MoneyNormalizer::toCents(19.995))->toBe(2000);
        expect(MoneyNormalizer::toCents(19.994))->toBe(1999);
    });

    it('converts string dollars with decimal to cents', function (): void {
        expect(MoneyNormalizer::toCents('19.99'))->toBe(1999);
        expect(MoneyNormalizer::toCents('99.00'))->toBe(9900);
    });

    it('treats string without decimal as cents', function (): void {
        expect(MoneyNormalizer::toCents('1999'))->toBe(1999);
        expect(MoneyNormalizer::toCents('100'))->toBe(100);
    });

    it('strips currency symbols', function (): void {
        expect(MoneyNormalizer::toCents('$19.99'))->toBe(1999);
        expect(MoneyNormalizer::toCents('€99.00'))->toBe(9900);
        expect(MoneyNormalizer::toCents('£50.50'))->toBe(5050);
        expect(MoneyNormalizer::toCents('RM19.99'))->toBe(1999);
    });

    it('strips thousands separators', function (): void {
        expect(MoneyNormalizer::toCents('1,999.99'))->toBe(199999);
        expect(MoneyNormalizer::toCents('$1,000.00'))->toBe(100000);
    });

    it('handles empty string as zero', function (): void {
        expect(MoneyNormalizer::toCents(''))->toBe(0);
        expect(MoneyNormalizer::toCents('  '))->toBe(0);
    });

    it('throws on invalid price format', function (): void {
        MoneyNormalizer::toCents('invalid');
    })->throws(InvalidArgumentException::class);

    it('throws on non-finite float', function (): void {
        MoneyNormalizer::toCents(INF);
    })->throws(InvalidArgumentException::class);
});

describe('MoneyNormalizer::toDollars', function (): void {
    it('converts cents to dollars', function (): void {
        expect(MoneyNormalizer::toDollars(1999))->toBe(19.99);
        expect(MoneyNormalizer::toDollars(100))->toBe(1.0);
        expect(MoneyNormalizer::toDollars(0))->toBe(0.0);
    });
});

describe('MoneyNormalizer::format', function (): void {
    it('formats cents as currency string', function (): void {
        $formatted = MoneyNormalizer::format(1999, 'USD', 'en_US');
        expect($formatted)->toContain('19.99');
    });
});
