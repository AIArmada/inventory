<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use AIArmada\Signals\Services\JourneyReportService;
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

final class JourneyReport extends Page implements HasTable
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

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Journeys';

    protected static ?string $title = 'Journeys';

    protected static ?string $slug = 'signals/journeys';

    protected string $view = 'filament-signals::pages.journey-report';

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
        return (int) config('filament-signals.resources.navigation_sort.journeys', 18);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.journeys', true);
    }

    /**
     * @return array{sessions:int,unique_entry_paths:int,unique_exit_paths:int,bounced_sessions:int,avg_duration_seconds:float}
     */
    public function getSummary(): array
    {
        return app(JourneyReportService::class)->summary(
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
            ->query(app(JourneyReportService::class)->getTableQuery(
                $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
                $this->dateFrom,
                $this->dateTo,
                $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
                $this->savedReportId !== '' ? $this->savedReportId : null,
            ))
            ->defaultSort('sessions', 'desc')
            ->columns([
                TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                TextColumn::make('journey_breakdown_value')
                    ->label(app(JourneyReportService::class)->getBreakdownLabel($this->savedReportId !== '' ? $this->savedReportId : null))
                    ->formatStateUsing(function (mixed $state, mixed $record): string {
                        if (($record->journey_breakdown_label ?? null) === 'Path Pair') {
                            return sprintf('%s -> %s', (string) ($record->journey_entry_path ?? '(unknown)'), (string) ($record->journey_exit_path ?? '(unknown)'));
                        }

                        return (string) $state;
                    })
                    ->searchable(['entry_path', 'exit_path', 'country', 'device_type', 'browser', 'os', 'utm_source', 'utm_medium', 'utm_campaign']),
                TextColumn::make('sessions')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('bounced_sessions')
                    ->label('Bounces')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('avg_duration_seconds')
                    ->label('Avg Duration')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 2) . 's')
                    ->sortable(),
                TextColumn::make('last_started_at')
                    ->label('Last Started')
                    ->formatStateUsing(fn (mixed $state): ?string => $this->formatAggregateTimestamp($state))
                    ->sortable(),
            ])
            ->emptyStateHeading('No journeys recorded yet')
            ->emptyStateDescription('Entry and exit paths will appear here once sessions start collecting navigation data.');
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getDateRangeHeaderActions(
                app(JourneyReportService::class)->getTrackedPropertyOptions(),
                app(SignalSegmentReportFilter::class)->getSegmentOptions(),
            ),
            Action::make('savedJourney')
                ->label('Saved Journey')
                ->schema([
                    Select::make('savedReportId')
                        ->label('Saved Report')
                        ->options(app(JourneyReportService::class)->getSavedReportOptions())
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
