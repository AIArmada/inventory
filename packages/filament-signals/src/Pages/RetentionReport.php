<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use AIArmada\Signals\Services\RetentionReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

final class RetentionReport extends Page
{
    use InteractsWithSignalsDateRange;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $trackedPropertyId = '';

    #[Url]
    public string $signalSegmentId = '';

    #[Url]
    public string $savedReportId = '';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Retention';

    protected static ?string $title = 'Retention';

    protected static ?string $slug = 'signals/retention';

    protected string $view = 'filament-signals::pages.retention-report';

    public function mount(): void
    {
        $this->initializeDefaultDateRange();
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.retention', 19);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.retention', true);
    }

    /**
     * @return array{cohorts:int,identities:int,windows:list<array{days:int,retained:int,avg_retention_rate:float}>}
     */
    public function getSummary(): array
    {
        return app(RetentionReportService::class)->summary(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
            $this->savedReportId !== '' ? $this->savedReportId : null,
        );
    }

    /**
     * @return list<array{cohort_date:string,cohort_size:int,windows:list<array{days:int,retained:int,retention_rate:float}>}>
     */
    public function getRows(): array
    {
        return app(RetentionReportService::class)->rows(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
            $this->savedReportId !== '' ? $this->savedReportId : null,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getDateRangeHeaderActions(
                app(RetentionReportService::class)->getTrackedPropertyOptions(),
                app(SignalSegmentReportFilter::class)->getSegmentOptions(),
            ),
            Action::make('savedRetention')
                ->label('Saved Retention')
                ->schema([
                    Select::make('savedReportId')
                        ->label('Saved Report')
                        ->options(app(RetentionReportService::class)->getSavedReportOptions())
                        ->searchable()
                        ->preload(),
                ])
                ->fillForm([
                    'savedReportId' => $this->savedReportId,
                ])
                ->action(function (array $data): void {
                    $this->savedReportId = (string) ($data['savedReportId'] ?? '');
                }),
        ];
    }
}
