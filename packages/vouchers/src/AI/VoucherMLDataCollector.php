<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

/**
 * Collects training data for ML model development.
 *
 * This class exports voucher application and conversion data
 * in a format suitable for training ML models externally
 * (e.g., AWS SageMaker, Python scikit-learn, etc.)
 */
final class VoucherMLDataCollector
{
    /**
     * Collect training data for conversion prediction.
     *
     * @param  Carbon  $from  Start date
     * @param  Carbon  $to  End date
     * @return Collection<int, array<string, mixed>>
     */
    public function collectConversionData(Carbon $from, Carbon $to): Collection
    {
        $voucherUsageTable = config('vouchers.database.tables.voucher_usage', 'voucher_usage');
        $vouchersTable = config('vouchers.database.tables.vouchers', 'vouchers');
        $cartsTable = config('cart.database.tables.carts', 'carts');
        $ordersTable = config('orders.database.tables.orders', 'orders');

        $query = DB::table($voucherUsageTable)
            ->join($vouchersTable, "{$vouchersTable}.id", '=', "{$voucherUsageTable}.voucher_id")
            ->join($cartsTable, "{$cartsTable}.id", '=', "{$voucherUsageTable}.cart_id")
            ->leftJoin($ordersTable, "{$ordersTable}.cart_id", '=', "{$cartsTable}.id")
            ->whereBetween("{$voucherUsageTable}.created_at", [$from, $to]);

        $this->applyOwnerScopeToQuery($query, 'vouchers.owner', "{$vouchersTable}.owner_type", "{$vouchersTable}.owner_id");
        $this->applyOwnerScopeToQuery($query, 'cart.owner', "{$cartsTable}.owner_type", "{$cartsTable}.owner_id");
        $this->applyOwnerScopeToQuery($query, 'orders.owner', "{$ordersTable}.owner_type", "{$ordersTable}.owner_id");

        return $query
            ->select([
                // Identifiers
                "{$voucherUsageTable}.id as usage_id",
                "{$voucherUsageTable}.voucher_id",
                "{$voucherUsageTable}.cart_id",
                "{$voucherUsageTable}.user_id",

                // Cart features
                "{$cartsTable}.subtotal_cents as cart_value_cents",
                "{$cartsTable}.item_count",

                // Voucher application
                "{$voucherUsageTable}.discount_cents",
                DB::raw("CASE 
                    WHEN {$cartsTable}.subtotal_cents > 0 
                    THEN ({$voucherUsageTable}.discount_cents * 100.0 / {$cartsTable}.subtotal_cents) 
                    ELSE 0 
                END as discount_percentage"),

                // Time features
                DB::raw($this->sqlHourOfDay("{$voucherUsageTable}.created_at") . ' as hour_of_day'),
                DB::raw($this->sqlDayOfWeekSunday1("{$voucherUsageTable}.created_at") . ' as day_of_week'),

                // Target variable
                DB::raw("CASE WHEN {$ordersTable}.id IS NOT NULL THEN 1 ELSE 0 END as converted"),

                // Additional context
                "{$voucherUsageTable}.created_at as applied_at",
                "{$ordersTable}.created_at as converted_at",
            ])
            ->get();
    }

    /**
     * Collect training data for abandonment prediction.
     *
     * @param  Carbon  $from  Start date
     * @param  Carbon  $to  End date
     * @return Collection<int, array<string, mixed>>
     */
    public function collectAbandonmentData(Carbon $from, Carbon $to): Collection
    {
        $cartsTable = config('cart.database.tables.carts', 'carts');
        $ordersTable = config('orders.database.tables.orders', 'orders');

        $query = DB::table($cartsTable)
            ->leftJoin($ordersTable, "{$ordersTable}.cart_id", '=', "{$cartsTable}.id")
            ->whereBetween("{$cartsTable}.created_at", [$from, $to])
            ->where("{$cartsTable}.item_count", '>', 0);

        $this->applyOwnerScopeToQuery($query, 'cart.owner', "{$cartsTable}.owner_type", "{$cartsTable}.owner_id");
        $this->applyOwnerScopeToQuery($query, 'orders.owner', "{$ordersTable}.owner_type", "{$ordersTable}.owner_id");

        return $query
            ->select([
                // Identifiers
                "{$cartsTable}.id as cart_id",
                "{$cartsTable}.user_id",

                // Cart features
                "{$cartsTable}.subtotal_cents as cart_value_cents",
                "{$cartsTable}.item_count",
                "{$cartsTable}.conditions_count",

                // Time features
                DB::raw($this->sqlHourOfDay("{$cartsTable}.created_at") . ' as hour_of_day'),
                DB::raw($this->sqlDayOfWeekSunday1("{$cartsTable}.created_at") . ' as day_of_week'),

                // Cart age in minutes (portable across supported DBs)
                DB::raw($this->sqlDiffMinutes("{$cartsTable}.created_at", "{$cartsTable}.updated_at") . ' as cart_age_minutes'),

                // Target variable (1 = abandoned, 0 = converted)
                DB::raw("CASE WHEN {$ordersTable}.id IS NULL THEN 1 ELSE 0 END as abandoned"),

                // Additional context
                "{$cartsTable}.created_at as cart_created_at",
                "{$ordersTable}.created_at as order_created_at",
            ])
            ->get();
    }

    private function sqlHourOfDay(string $qualifiedColumn): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "EXTRACT(HOUR FROM {$qualifiedColumn})",
            'sqlite' => "CAST(STRFTIME('%H', {$qualifiedColumn}) AS INTEGER)",
            default => "HOUR({$qualifiedColumn})",
        };
    }

    /**
     * Returns day-of-week as integer 1..7 with Sunday = 1.
     */
    private function sqlDayOfWeekSunday1(string $qualifiedColumn): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "(EXTRACT(DOW FROM {$qualifiedColumn}) + 1)",
            'sqlite' => "(CAST(STRFTIME('%w', {$qualifiedColumn}) AS INTEGER) + 1)",
            default => "DAYOFWEEK({$qualifiedColumn})",
        };
    }

    private function sqlDiffMinutes(string $qualifiedStartColumn, string $qualifiedEndColumn): string
    {
        $driver = DB::connection()->getDriverName();
        $end = "COALESCE({$qualifiedEndColumn}, {$qualifiedStartColumn})";

        return match ($driver) {
            'pgsql' => "FLOOR(EXTRACT(EPOCH FROM ({$end} - {$qualifiedStartColumn})) / 60)",
            'sqlite' => "CAST((JULIANDAY({$end}) - JULIANDAY({$qualifiedStartColumn})) * 24 * 60 AS INTEGER)",
            default => "TIMESTAMPDIFF(MINUTE, {$qualifiedStartColumn}, {$end})",
        };
    }

    /**
     * Collect voucher performance data for optimization.
     *
     * @param  Carbon  $from  Start date
     * @param  Carbon  $to  End date
     * @return Collection<int, array<string, mixed>>
     */
    public function collectVoucherPerformanceData(Carbon $from, Carbon $to): Collection
    {
        $vouchersTable = config('vouchers.database.tables.vouchers', 'vouchers');
        $voucherUsageTable = config('vouchers.database.tables.voucher_usage', 'voucher_usage');
        $ordersTable = config('orders.database.tables.orders', 'orders');

        $query = DB::table($vouchersTable)
            ->leftJoin($voucherUsageTable, "{$voucherUsageTable}.voucher_id", '=', "{$vouchersTable}.id")
            ->leftJoin($ordersTable, function ($join) use ($voucherUsageTable, $ordersTable): void {
                $join->on("{$ordersTable}.cart_id", '=', "{$voucherUsageTable}.cart_id");
            })
            ->whereBetween("{$vouchersTable}.created_at", [$from, $to])
            ->groupBy("{$vouchersTable}.id");

        $this->applyOwnerScopeToQuery($query, 'vouchers.owner', "{$vouchersTable}.owner_type", "{$vouchersTable}.owner_id");

        return $query
            ->select([
                // Voucher info
                "{$vouchersTable}.id as voucher_id",
                "{$vouchersTable}.code",
                "{$vouchersTable}.type",
                "{$vouchersTable}.value",
                "{$vouchersTable}.min_cart_value",
                "{$vouchersTable}.usage_limit",

                // Performance metrics
                DB::raw("COUNT({$voucherUsageTable}.id) as total_applications"),
                DB::raw("COUNT({$ordersTable}.id) as conversions"),
                DB::raw("SUM({$voucherUsageTable}.discount_cents) as total_discount_given"),
                DB::raw("AVG({$voucherUsageTable}.discount_cents) as avg_discount"),

                // Conversion rate
                DB::raw("CASE 
                    WHEN COUNT({$voucherUsageTable}.id) > 0 
                    THEN COUNT({$ordersTable}.id) * 1.0 / COUNT({$voucherUsageTable}.id) 
                    ELSE 0 
                END as conversion_rate"),
            ])
            ->get();
    }

    /**
     * Export data to CSV format.
     */
    public function exportToCsv(Collection $data, string $filepath): void
    {
        if ($data->isEmpty()) {
            return;
        }

        $directory = dirname($filepath);
        if (! is_dir($directory)) {
            throw new RuntimeException("Directory does not exist: {$directory}");
        }

        $handle = fopen($filepath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file for writing: {$filepath}");
        }

        try {
            // Write headers
            fputcsv($handle, array_keys((array) $data->first()));

            // Write data rows
            foreach ($data as $row) {
                fputcsv($handle, (array) $row);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Export data to JSON format.
     */
    public function exportToJson(Collection $data, string $filepath): void
    {
        $directory = dirname($filepath);
        if (! is_dir($directory)) {
            throw new RuntimeException("Directory does not exist: {$directory}");
        }

        try {
            $json = json_encode($data->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode JSON for export.', previous: $exception);
        }

        $bytes = file_put_contents($filepath, $json, LOCK_EX);

        if ($bytes === false) {
            throw new RuntimeException("Failed to write JSON export to: {$filepath}");
        }
    }

    /**
     * Get summary statistics for the collected data.
     *
     * @return array<string, mixed>
     */
    public function getSummaryStatistics(Collection $data): array
    {
        if ($data->isEmpty()) {
            return [
                'count' => 0,
                'columns' => [],
            ];
        }

        $first = (array) $data->first();
        $columns = [];

        foreach (array_keys($first) as $column) {
            $values = $data->pluck($column)->filter(fn ($v) => is_numeric($v));

            if ($values->isNotEmpty()) {
                $columns[$column] = [
                    'type' => 'numeric',
                    'min' => $values->min(),
                    'max' => $values->max(),
                    'avg' => round($values->avg(), 2),
                    'count' => $values->count(),
                ];
            } else {
                $uniqueValues = $data->pluck($column)->unique()->count();
                $columns[$column] = [
                    'type' => 'categorical',
                    'unique_values' => $uniqueValues,
                    'count' => $data->count(),
                ];
            }
        }

        return [
            'count' => $data->count(),
            'columns' => $columns,
        ];
    }

    private function applyOwnerScopeToQuery($query, string $configKey, string $ownerTypeColumn, string $ownerIdColumn): void
    {
        if (! (bool) config($configKey . '.enabled', false)) {
            return;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config($configKey . '.include_global', false);

        OwnerQuery::applyToQueryBuilder($query, $owner, $includeGlobal, $ownerTypeColumn, $ownerIdColumn);
    }
}
