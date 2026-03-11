<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use AIArmada\Signals\Services\AcquisitionReportService;
use AIArmada\Signals\Services\SavedSignalReportDefinition;
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

final class AcquisitionReport extends Page implements HasTable
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
    public string $attributionModel = '';

    #[Url]
    public string $savedReportId = '';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $navigationLabel = 'Acquisition';

    protected static ?string $title = 'Acquisition';

    protected static ?string $slug = 'signals/acquisition';

    protected string $view = 'filament-signals::pages.acquisition-report';

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
        return (int) config('filament-signals.resources.navigation_sort.acquisition', 17);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.acquisition', true);
    }

    /**
     * @return array{attributed_events:int,visitors:int,conversions:int,revenue_minor:int,campaigns:int}
     */
    public function getSummary(): array
    {
        return app(AcquisitionReportService::class)->summary(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
            $this->attributionModel,
            $this->savedReportId !== '' ? $this->savedReportId : null,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(app(AcquisitionReportService::class)->getTableQuery(
                $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
                $this->dateFrom,
                $this->dateTo,
                $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
                $this->attributionModel,
                $this->savedReportId !== '' ? $this->savedReportId : null,
            ))
            ->defaultSort('visitors', 'desc')
            ->columns([
                TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                TextColumn::make('acquisition_source')
                    ->label('Source')
                    ->badge(),
                TextColumn::make('acquisition_medium')
                    ->label('Medium')
                    ->badge(),
                TextColumn::make('acquisition_campaign')
                    ->label('Campaign')
                    ->searchable(['campaign']),
                TextColumn::make('acquisition_content')
                    ->label('Content')
                    ->searchable(['content'])
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('acquisition_term')
                    ->label('Term')
                    ->searchable(['term'])
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('acquisition_referrer')
                    ->label('Referrer')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('events')
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
            ->emptyStateHeading('No acquisition data recorded yet')
            ->emptyStateDescription('Source, campaign, medium, and referrer data will appear here once traffic events are being collected.');
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getDateRangeHeaderActions(
                app(AcquisitionReportService::class)->getTrackedPropertyOptions(),
                app(SignalSegmentReportFilter::class)->getSegmentOptions(),
            ),
            Action::make('savedReport')
                ->label('Saved Report')
                ->icon(Heroicon::OutlinedBookmarkSquare)
                ->form([
                    Select::make('saved_report_id')
                        ->label('Saved Acquisition Report')
                        ->options(app(AcquisitionReportService::class)->getSavedReportOptions())
                        ->default($this->savedReportId !== '' ? $this->savedReportId : null)
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $this->savedReportId = is_string($data['saved_report_id'] ?? null)
                        ? $data['saved_report_id']
                        : '';
                }),
            Action::make('eventTouch')
                ->label('Event Touch')
                ->outlined()
                ->action(fn (): string => $this->attributionModel = SavedSignalReportDefinition::ATTRIBUTION_MODEL_EVENT),
            Action::make('firstTouch')
                ->label('First Touch')
                ->outlined()
                ->action(fn (): string => $this->attributionModel = SavedSignalReportDefinition::ATTRIBUTION_MODEL_FIRST_TOUCH),
            Action::make('lastTouch')
                ->label('Last Touch')
                ->outlined()
                ->action(fn (): string => $this->attributionModel = SavedSignalReportDefinition::ATTRIBUTION_MODEL_LAST_TOUCH),
        ];
    }
}
