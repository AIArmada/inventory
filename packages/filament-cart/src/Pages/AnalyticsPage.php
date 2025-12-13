<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Pages;

use AIArmada\FilamentCart\Services\ExportService;
use AIArmada\FilamentCart\Widgets\AbandonmentAnalysisWidget;
use AIArmada\FilamentCart\Widgets\AnalyticsStatsWidget;
use AIArmada\FilamentCart\Widgets\ConversionFunnelWidget;
use AIArmada\FilamentCart\Widgets\RecoveryPerformanceWidget;
use AIArmada\FilamentCart\Widgets\ValueTrendChartWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Advanced Cart Analytics page with comprehensive reporting.
 *
 * Provides detailed analytics, conversion funnel visualization,
 * abandonment analysis, and recovery performance tracking.
 */
class AnalyticsPage extends Page
{
    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $interval = 'day';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string | UnitEnum | null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament-cart::pages.analytics';

    protected static ?string $title = 'Cart Analytics Report';

    protected static ?string $slug = 'cart-analytics';

    public static function getNavigationLabel(): string
    {
        return 'Analytics Report';
    }

    public function mount(): void
    {
        if (empty($this->dateFrom)) {
            $this->dateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        }

        if (empty($this->dateTo)) {
            $this->dateTo = Carbon::now()->format('Y-m-d');
        }
    }

    public function getDateFrom(): Carbon
    {
        return Carbon::parse($this->dateFrom);
    }

    public function getDateTo(): Carbon
    {
        return Carbon::parse($this->dateTo);
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dateRange')
                ->label('Date Range')
                ->icon('heroicon-o-calendar')
                ->form([
                    Grid::make(2)
                        ->schema([
                            DatePicker::make('from')
                                ->label('From')
                                ->default($this->dateFrom)
                                ->required(),
                            DatePicker::make('to')
                                ->label('To')
                                ->default($this->dateTo)
                                ->required(),
                        ]),
                    Select::make('interval')
                        ->label('Group By')
                        ->options([
                            'day' => 'Day',
                            'week' => 'Week',
                            'month' => 'Month',
                        ])
                        ->default($this->interval),
                ])
                ->action(function (array $data): void {
                    $this->dateFrom = Carbon::parse($data['from'])->format('Y-m-d');
                    $this->dateTo = Carbon::parse($data['to'])->format('Y-m-d');
                    $this->interval = $data['interval'] ?? 'day';

                    $this->dispatch('date-range-updated');
                }),

            Action::make('quick7Days')
                ->label('Last 7 Days')
                ->color('gray')
                ->action(fn () => $this->setQuickRange(7)),

            Action::make('quick30Days')
                ->label('Last 30 Days')
                ->color('gray')
                ->action(fn () => $this->setQuickRange(30)),

            Action::make('quick90Days')
                ->label('Last 90 Days')
                ->color('gray')
                ->action(fn () => $this->setQuickRange(90)),

            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportCsv()),

            Action::make('exportXlsx')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn () => $this->exportXlsx()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AnalyticsStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        $widgets = [
            ConversionFunnelWidget::class,
            ValueTrendChartWidget::class,
        ];

        if (config('filament-cart.features.abandonment_tracking', true)) {
            $widgets[] = AbandonmentAnalysisWidget::class;
        }

        if (config('filament-cart.features.ai_recovery', true)) {
            $widgets[] = RecoveryPerformanceWidget::class;
        }

        return $widgets;
    }

    private function setQuickRange(int $days): void
    {
        $this->dateFrom = Carbon::now()->subDays($days)->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');

        $this->dispatch('date-range-updated');
    }

    private function exportCsv(): StreamedResponse
    {
        $exportService = app(ExportService::class);
        $csv = $exportService->exportMetricsToCsv(
            $this->getDateFrom(),
            $this->getDateTo(),
        );

        $filename = sprintf(
            'cart-analytics-%s-to-%s.csv',
            $this->dateFrom,
            $this->dateTo,
        );

        Notification::make()
            ->title('Export Started')
            ->body('Your CSV export is being downloaded.')
            ->success()
            ->send();

        return Response::streamDownload(
            fn () => print $csv,
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    private function exportXlsx(): StreamedResponse
    {
        $exportService = app(ExportService::class);
        $filePath = $exportService->exportToXlsx(
            $this->getDateFrom(),
            $this->getDateTo(),
        );

        $filename = sprintf(
            'cart-analytics-%s-to-%s.xlsx',
            $this->dateFrom,
            $this->dateTo,
        );

        Notification::make()
            ->title('Export Started')
            ->body('Your Excel export is being downloaded.')
            ->success()
            ->send();

        return Response::streamDownload(
            function () use ($filePath) {
                echo file_get_contents($filePath);
                unlink($filePath);
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
