<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Models\CartDailyMetrics;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Service for exporting analytics data.
 */
class ExportService
{
    public function __construct(
        private CartAnalyticsService $analyticsService,
    ) {}

    /**
     * Export dashboard metrics to CSV.
     */
    public function exportMetricsToCsv(Carbon $from, Carbon $to): string
    {
        $metrics = CartDailyMetrics::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('segment')
            ->orderBy('date')
            ->get();

        $headers = [
            'Date',
            'Carts Created',
            'Active Carts',
            'Empty Carts',
            'Carts with Items',
            'Checkouts Started',
            'Checkouts Completed',
            'Checkouts Abandoned',
            'Recovery Emails',
            'Carts Recovered',
            'Recovered Revenue',
            'Total Cart Value',
            'Average Cart Value',
            'Total Items',
            'Avg Items/Cart',
            'Fraud High',
            'Fraud Medium',
            'Carts Blocked',
            'Collaborative Carts',
            'Collaborators',
            'Conversion Rate',
            'Abandonment Rate',
            'Recovery Rate',
        ];

        $csv = implode(',', $headers) . "\n";

        foreach ($metrics as $metric) {
            $row = [
                $metric->date->format('Y-m-d'),
                $metric->carts_created,
                $metric->carts_active,
                $metric->carts_empty,
                $metric->carts_with_items,
                $metric->checkouts_started,
                $metric->checkouts_completed,
                $metric->checkouts_abandoned,
                $metric->recovery_emails_sent,
                $metric->carts_recovered,
                number_format($metric->recovered_revenue_cents / 100, 2),
                number_format($metric->total_cart_value_cents / 100, 2),
                number_format($metric->average_cart_value_cents / 100, 2),
                $metric->total_items,
                $metric->average_items_per_cart,
                $metric->fraud_alerts_high,
                $metric->fraud_alerts_medium,
                $metric->carts_blocked,
                $metric->collaborative_carts,
                $metric->total_collaborators,
                number_format($metric->getConversionRate() * 100, 2) . '%',
                number_format($metric->getAbandonmentRate() * 100, 2) . '%',
                number_format($metric->getRecoveryRate() * 100, 2) . '%',
            ];

            $csv .= implode(',', $row) . "\n";
        }

        return $csv;
    }

    /**
     * Export complete analytics report to XLSX.
     */
    public function exportToXlsx(Carbon $from, Carbon $to): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cart_analytics_') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($tempFile);

        // Overview Sheet
        $this->writeOverviewSheet($writer, $from, $to);

        // Daily Metrics Sheet
        $this->writeDailyMetricsSheet($writer, $from, $to);

        // Abandonment Analysis Sheet
        $this->writeAbandonmentSheet($writer, $from, $to);

        // Recovery Performance Sheet
        $this->writeRecoverySheet($writer, $from, $to);

        $writer->close();

        return $tempFile;
    }

    /**
     * Export data as JSON.
     *
     * @return array<string, mixed>
     */
    public function exportToJson(Carbon $from, Carbon $to): array
    {
        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'generated_at' => now()->toIso8601String(),
            'dashboard' => $this->analyticsService->getDashboardMetrics($from, $to)->toArray(),
            'conversion_funnel' => $this->analyticsService->getConversionFunnel($from, $to)->toArray(),
            'recovery_metrics' => $this->analyticsService->getRecoveryMetrics($from, $to)->toArray(),
            'value_trends' => $this->analyticsService->getValueTrends($from, $to),
            'abandonment_analysis' => $this->analyticsService->getAbandonmentAnalysis($from, $to)->toArray(),
        ];
    }

    /**
     * Write overview sheet.
     */
    private function writeOverviewSheet(Writer $writer, Carbon $from, Carbon $to): void
    {
        $writer->getCurrentSheet()->setName('Overview');

        $headerStyle = (new Style())
            ->setFontBold()
            ->setCellAlignment(CellAlignment::CENTER);

        $metrics = $this->analyticsService->getDashboardMetrics($from, $to);
        $funnel = $this->analyticsService->getConversionFunnel($from, $to);
        $recovery = $this->analyticsService->getRecoveryMetrics($from, $to);

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Cart Analytics Report',
        ], $headerStyle));

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Period: ' . $from->format('Y-m-d') . ' to ' . $to->format('Y-m-d'),
        ]));

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        // Key Metrics
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Key Metrics',
        ], $headerStyle));

        $this->addMetricRow($writer, 'Total Carts', (string) $metrics->total_carts);
        $this->addMetricRow($writer, 'Active Carts', (string) $metrics->active_carts);
        $this->addMetricRow($writer, 'Abandoned Carts', (string) $metrics->abandoned_carts);
        $this->addMetricRow($writer, 'Recovered Carts', (string) $metrics->recovered_carts);
        $this->addMetricRow($writer, 'Total Value', '$' . number_format($metrics->total_value_cents / 100, 2));
        $this->addMetricRow($writer, 'Average Cart Value', '$' . number_format($metrics->average_cart_value_cents / 100, 2));

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        // Rates
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Performance Rates',
        ], $headerStyle));

        $this->addMetricRow($writer, 'Conversion Rate', number_format($metrics->conversion_rate * 100, 2) . '%');
        $this->addMetricRow($writer, 'Abandonment Rate', number_format($metrics->abandonment_rate * 100, 2) . '%');
        $this->addMetricRow($writer, 'Recovery Rate', number_format($metrics->recovery_rate * 100, 2) . '%');

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        // Funnel
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Conversion Funnel',
        ], $headerStyle));

        $this->addMetricRow($writer, 'Carts Created', (string) $funnel->carts_created);
        $this->addMetricRow($writer, 'Items Added', (string) $funnel->items_added);
        $this->addMetricRow($writer, 'Checkout Started', (string) $funnel->checkout_started);
        $this->addMetricRow($writer, 'Checkout Completed', (string) $funnel->checkout_completed);
        $this->addMetricRow($writer, 'Overall Drop-off', number_format($funnel->getOverallDropOffRate() * 100, 2) . '%');

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        // Recovery
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Recovery Performance',
        ], $headerStyle));

        $this->addMetricRow($writer, 'Total Abandoned', (string) $recovery->total_abandoned);
        $this->addMetricRow($writer, 'Recovery Attempts', (string) $recovery->recovery_attempts);
        $this->addMetricRow($writer, 'Successful Recoveries', (string) $recovery->successful_recoveries);
        $this->addMetricRow($writer, 'Recovered Revenue', '$' . number_format($recovery->recovered_revenue_cents / 100, 2));
        $this->addMetricRow($writer, 'Recovery Rate', number_format($recovery->recovery_rate * 100, 2) . '%');
    }

    /**
     * Write daily metrics sheet.
     */
    private function writeDailyMetricsSheet(Writer $writer, Carbon $from, Carbon $to): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Daily Metrics');

        $headerStyle = (new Style())
            ->setFontBold()
            ->setCellAlignment(CellAlignment::CENTER);

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Date',
            'Carts Created',
            'Active',
            'With Items',
            'Checkouts Started',
            'Completed',
            'Abandoned',
            'Recovered',
            'Total Value',
            'Avg Value',
            'Conversion %',
            'Abandonment %',
            'Recovery %',
        ], $headerStyle));

        $metrics = CartDailyMetrics::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('segment')
            ->orderBy('date')
            ->get();

        foreach ($metrics as $metric) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                $metric->date->format('Y-m-d'),
                $metric->carts_created,
                $metric->carts_active,
                $metric->carts_with_items,
                $metric->checkouts_started,
                $metric->checkouts_completed,
                $metric->checkouts_abandoned,
                $metric->carts_recovered,
                number_format($metric->total_cart_value_cents / 100, 2),
                number_format($metric->average_cart_value_cents / 100, 2),
                number_format($metric->getConversionRate() * 100, 2),
                number_format($metric->getAbandonmentRate() * 100, 2),
                number_format($metric->getRecoveryRate() * 100, 2),
            ]));
        }
    }

    /**
     * Write abandonment analysis sheet.
     */
    private function writeAbandonmentSheet(Writer $writer, Carbon $from, Carbon $to): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Abandonment Analysis');

        $analysis = $this->analyticsService->getAbandonmentAnalysis($from, $to);

        $headerStyle = (new Style())
            ->setFontBold()
            ->setCellAlignment(CellAlignment::CENTER);

        // By Hour
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Abandonments by Hour',
        ], $headerStyle));

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Hour',
            'Count',
        ], $headerStyle));

        foreach ($analysis->by_hour as $hour => $count) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                sprintf('%02d:00', $hour),
                $count,
            ]));
        }

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        // By Day of Week
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Abandonments by Day of Week',
        ], $headerStyle));

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Day',
            'Count',
        ], $headerStyle));

        foreach ($analysis->by_day_of_week as $day => $count) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                $days[$day],
                $count,
            ]));
        }

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        // By Cart Value Range
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Abandonments by Cart Value',
        ], $headerStyle));

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Value Range',
            'Count',
        ], $headerStyle));

        foreach ($analysis->by_cart_value_range as $range => $count) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                $range,
                $count,
            ]));
        }

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        // Exit Points
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Common Exit Points',
        ], $headerStyle));

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Exit Point',
            'Count',
        ], $headerStyle));

        foreach ($analysis->common_exit_points as $point => $count) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                $point,
                $count,
            ]));
        }
    }

    /**
     * Write recovery performance sheet.
     */
    private function writeRecoverySheet(Writer $writer, Carbon $from, Carbon $to): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Recovery Performance');

        $recovery = $this->analyticsService->getRecoveryMetrics($from, $to);

        $headerStyle = (new Style())
            ->setFontBold()
            ->setCellAlignment(CellAlignment::CENTER);

        // Overall
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Recovery Performance Summary',
        ], $headerStyle));

        $this->addMetricRow($writer, 'Total Abandoned', (string) $recovery->total_abandoned);
        $this->addMetricRow($writer, 'Recovery Attempts', (string) $recovery->recovery_attempts);
        $this->addMetricRow($writer, 'Successful Recoveries', (string) $recovery->successful_recoveries);
        $this->addMetricRow($writer, 'Recovered Revenue', '$' . number_format($recovery->recovered_revenue_cents / 100, 2));
        $this->addMetricRow($writer, 'Recovery Rate', number_format($recovery->recovery_rate * 100, 2) . '%');

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        // By Strategy
        if (! empty($recovery->by_strategy)) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                'Performance by Strategy',
            ], $headerStyle));

            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                'Strategy',
                'Attempts',
                'Conversions',
                'Revenue',
                'Conversion Rate',
            ], $headerStyle));

            foreach ($recovery->by_strategy as $strategy => $data) {
                $rate = $data['attempts'] > 0
                    ? ($data['conversions'] / $data['attempts']) * 100
                    : 0;

                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                    ucfirst($strategy),
                    $data['attempts'],
                    $data['conversions'],
                    '$' . number_format($data['revenue'] / 100, 2),
                    number_format($rate, 2) . '%',
                ]));
            }
        }
    }

    /**
     * Add a metric row.
     */
    private function addMetricRow(Writer $writer, string $label, string $value): void
    {
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            $label,
            $value,
        ]));
    }
}
