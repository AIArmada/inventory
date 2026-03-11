<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use AIArmada\Signals\Services\LiveActivityReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

final class LiveActivityReport extends Page implements HasTable
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

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Live Activity';

    protected static ?string $title = 'Live Activity';

    protected static ?string $slug = 'signals/live-activity';

    protected string $view = 'filament-signals::pages.live-activity-report';

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
        return (int) config('filament-signals.resources.navigation_sort.live_activity', 21);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.live_activity', true);
    }

    /**
     * @return array{events:int,page_views:int,conversions:int,revenue_minor:int}
     */
    public function getSummary(): array
    {
        return app(LiveActivityReportService::class)->summary(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(app(LiveActivityReportService::class)->getTableQuery(
                $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
                $this->dateFrom,
                $this->dateTo,
                $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
            ))
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->formatStateUsing(fn (mixed $state): ?string => $this->formatAggregateTimestamp($state))
                    ->sortable(),
                TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                TextColumn::make('event_name')
                    ->badge()
                    ->searchable(),
                TextColumn::make('event_category')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('path')
                    ->label('Path')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('identity.external_id')
                    ->label('Identity')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('session.session_identifier')
                    ->label('Session')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('revenue_minor')
                    ->label($this->monetaryValueLabel())
                    ->formatStateUsing(fn (mixed $state): string => $this->formatMoney((int) $state))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('No live activity recorded yet')
            ->emptyStateDescription('Recent events will appear here as soon as Signals starts capturing traffic and outcomes.');
    }

    protected function getHeaderActions(): array
    {
        return $this->getDateRangeHeaderActions(
            app(LiveActivityReportService::class)->getTrackedPropertyOptions(),
            app(SignalSegmentReportFilter::class)->getSegmentOptions(),
        );
    }
}
