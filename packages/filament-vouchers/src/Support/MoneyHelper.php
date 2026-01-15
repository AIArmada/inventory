<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\CommerceSupport\Support\MoneyNormalizer;
use Akaunting\Money\Money;

/**
 * Helper class for money and percentage conversions in Filament forms.
 *
 * This extends commerce-support's MoneyNormalizer with Filament-specific helpers:
 * - Form state formatting/dehydration for cents and basis points
 * - Akaunting\Money formatting integration
 *
 * Values are stored as integers:
 * - Fixed amounts: stored in cents (e.g., 1000 = $10.00)
 * - Percentages: stored in basis points (e.g., 1050 = 10.50%)
 */
final class MoneyHelper
{
    /**
     * Convert cents to display decimal for forms.
     *
     * @example 1000 -> "10.00"
     */
    public static function centsToDisplay(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        return number_format(MoneyNormalizer::toDollars($cents), 2, '.', '');
    }

    /**
     * Convert display decimal to cents for storage.
     *
     * @example "10.00" -> 1000
     */
    public static function displayToCents(?string $display): ?int
    {
        if ($display === null || $display === '') {
            return null;
        }

        return (int) round((float) $display * 100);
    }

    /**
     * Convert basis points to display percentage for forms.
     *
     * @example 1050 -> "10.50"
     */
    public static function basisPointsToDisplay(?int $basisPoints): ?string
    {
        if ($basisPoints === null) {
            return null;
        }

        return number_format($basisPoints / 100, 2, '.', '');
    }

    /**
     * Convert display percentage to basis points for storage.
     *
     * @example "10.50" -> 1050
     */
    public static function displayToBasisPoints(?string $display): ?int
    {
        if ($display === null || $display === '') {
            return null;
        }

        return (int) round((float) $display * 100);
    }

    /**
     * Format cents as a money string using Akaunting\Money.
     *
     * @example formatMoney(1000, 'MYR') -> "RM10.00"
     */
    public static function formatMoney(int $cents, string $currency = 'MYR'): string
    {
        $currency = mb_strtoupper($currency ?: self::defaultCurrency());

        return (string) Money::{$currency}($cents);
    }

    /**
     * Format basis points as a percentage string.
     *
     * @example formatPercentage(1050) -> "10.50%"
     */
    public static function formatPercentage(int $basisPoints): string
    {
        $percentage = $basisPoints / 100;

        return mb_rtrim(mb_rtrim(number_format($percentage, 2), '0'), '.') . '%';
    }

    /**
     * Get the default currency from config.
     */
    public static function defaultCurrency(): string
    {
        return mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));
    }
}
