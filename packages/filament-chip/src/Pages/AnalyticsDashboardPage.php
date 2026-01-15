<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages;

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Services\LocalAnalyticsService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AnalyticsDashboardPage extends Page
{
    public string $period = '30';

    public ?DashboardMetrics $metrics = null;

    /** @var array<int, array{period: string, count: int, revenue: int}> */
    public array $revenueTrend = [];

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Payment Analytics';

    protected static ?string $slug = 'chip/analytics';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament-chip::pages.analytics-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-chip.navigation.group', 'Payments');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->loadMetrics();
    }

    public function loadMetrics(): void
    {
        $service = app(LocalAnalyticsService::class);
        $endDate = CarbonImmutable::now();
        $startDate = $endDate->subDays((int) $this->period);

        $this->metrics = $service->getDashboardMetrics($startDate, $endDate);
        $this->revenueTrend = $service->getRevenueTrend($startDate, $endDate, 'day');
    }

    public function updatedPeriod(): void
    {
        $this->loadMetrics();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('period_7')
                ->label('7 Days')
                ->outlined()
                ->action(function (): void {
                    $this->period = '7';
                    $this->loadMetrics();
                }),

            Action::make('period_30')
                ->label('30 Days')
                ->outlined()
                ->action(function (): void {
                    $this->period = '30';
                    $this->loadMetrics();
                }),

            Action::make('period_90')
                ->label('90 Days')
                ->outlined()
                ->action(function (): void {
                    $this->period = '90';
                    $this->loadMetrics();
                }),

            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::ArrowPath)
                ->action(fn () => $this->loadMetrics()),
        ];
    }
}
