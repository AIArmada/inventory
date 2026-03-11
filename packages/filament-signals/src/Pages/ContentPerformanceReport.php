<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Services\ContentPerformanceReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

final class ContentPerformanceReport extends Page implements HasTable
{
    use FormatsSignalsReportValues;
    use InteractsWithSignalsDateRange;
    use InteractsWithTable;

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

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Content Performance';

    protected static ?string $title = 'Content Performance';

    protected static ?string $slug = 'signals/content-performance';

    protected string $view = 'filament-signals::pages.content-performance-report';

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
        return (int) config('filament-signals.resources.navigation_sort.content_performance', 20);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.content_performance', true);
    }

    /**
     * @return array{paths:int,views:int,conversions:int,revenue_minor:int,avg_conversion_rate:float}
     */
    public function getSummary(): array
    {
        return app(ContentPerformanceReportService::class)->summary(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
            $this->savedReportId !== '' ? $this->savedReportId : null,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(app(ContentPerformanceReportService::class)->getTableQuery(
                $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
                $this->dateFrom,
                $this->dateTo,
                $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
                $this->savedReportId !== '' ? $this->savedReportId : null,
            ))
            ->defaultSort('views', 'desc')
            ->columns([
                TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                TextColumn::make('content_breakdown_value')
                    ->label(app(ContentPerformanceReportService::class)->getBreakdownLabel($this->savedReportId !== '' ? $this->savedReportId : null))
                    ->searchable(['path', 'source', 'medium', 'campaign', 'referrer']),
                TextColumn::make('content_path')
                    ->label('Path')
                    ->searchable(['path'])
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('content_url')
                    ->label('URL')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->url(fn (SignalEvent $record): ?string => is_string($record->content_url ?? null) ? $record->content_url : null, shouldOpenInNewTab: true),
                TextColumn::make('views')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('visitors')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('conversions')
                    ->label($this->outcomesLabel())
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('revenue_minor')
                    ->label($this->monetaryValueLabel())
                    ->formatStateUsing(fn (mixed $state): string => $this->formatMoney((int) $state))
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->formatStateUsing(fn (mixed $state): ?string => $this->formatAggregateTimestamp($state))
                    ->sortable(),
            ])
            ->emptyStateHeading('No content performance data recorded yet')
            ->emptyStateDescription('Paths and outcome performance will appear here once page views and primary outcomes start flowing through Signals.');
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getDateRangeHeaderActions(
                app(ContentPerformanceReportService::class)->getTrackedPropertyOptions(),
                app(SignalSegmentReportFilter::class)->getSegmentOptions(),
            ),
            Action::make('savedContentReport')
                ->label('Saved Content Report')
                ->schema([
                    Select::make('savedReportId')
                        ->label('Saved Report')
                        ->options(app(ContentPerformanceReportService::class)->getSavedReportOptions())
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
