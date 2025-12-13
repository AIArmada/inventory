<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

use Spatie\LaravelData\Data;

/**
 * Abandonment analysis DTO.
 */
class AbandonmentAnalysis extends Data
{
    /**
     * @param  array<int, int>  $by_hour  Hour (0-23) => count
     * @param  array<int, int>  $by_day_of_week  Day (0-6) => count
     * @param  array<string, int>  $by_cart_value_range  Range label => count
     * @param  array<string, int>  $by_items_count  Items range => count
     * @param  array<string, int>  $common_exit_points  Exit point => count
     */
    public function __construct(
        public array $by_hour,
        public array $by_day_of_week,
        public array $by_cart_value_range,
        public array $by_items_count,
        public array $common_exit_points,
        public int $total_abandonments,
        public ?string $peak_abandonment_hour = null,
        public ?string $peak_abandonment_day = null,
    ) {}

    public static function fromData(
        array $byHour,
        array $byDayOfWeek,
        array $byCartValueRange,
        array $byItemsCount,
        array $commonExitPoints,
        int $totalAbandonments,
    ): self {
        $peakHour = ! empty($byHour) ? array_search(max($byHour), $byHour) : null;
        $peakDay = ! empty($byDayOfWeek) ? array_search(max($byDayOfWeek), $byDayOfWeek) : null;

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return new self(
            by_hour: $byHour,
            by_day_of_week: $byDayOfWeek,
            by_cart_value_range: $byCartValueRange,
            by_items_count: $byItemsCount,
            common_exit_points: $commonExitPoints,
            total_abandonments: $totalAbandonments,
            peak_abandonment_hour: $peakHour !== null ? sprintf('%02d:00', $peakHour) : null,
            peak_abandonment_day: $peakDay !== null ? ($dayNames[$peakDay] ?? null) : null,
        );
    }
}
