<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Pages;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SavedSignalReportDefinition;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class SavedSignalReportResource extends Resource
{
    protected static ?string $model = SavedSignalReport::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bookmark-square';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 32;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SavedSignalReport>
     */
    public static function getEloquentQuery(): Builder
    {
        return SavedSignalReport::query()->forOwner()->with(['trackedProperty', 'segment']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Saved Report')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('report_type')
                        ->options(SavedSignalReportDefinition::reportTypeOptions())
                        ->required(),

                    Forms\Components\Select::make('tracked_property_id')
                        ->label('Tracked Property')
                        ->relationship(
                            name: 'trackedProperty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                'id',
                                TrackedProperty::query()->forOwner()->select('id')
                            ),
                        )
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('signal_segment_id')
                        ->label('Segment')
                        ->relationship(
                            name: 'segment',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                'id',
                                SignalSegment::query()->forOwner()->select('id')
                            ),
                        )
                        ->searchable()
                        ->preload(),

                    Forms\Components\Toggle::make('is_shared')
                        ->default(false),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('filters')
                        ->schema([
                            Forms\Components\Select::make('key')
                                ->options(SavedSignalReportDefinition::filterFieldOptions())
                                ->required()
                                ->distinct(),
                            Forms\Components\TextInput::make('value')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('YYYY-MM-DD'),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->addActionLabel('Add Filter')
                        ->helperText('Supported filters: From Date and To Date.'),

                    Section::make('Funnel Settings')
                        ->schema([
                            Forms\Components\Repeater::make('settings.funnel_steps')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->required()
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('event_name')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('event_category')
                                        ->maxLength(100),
                                ])
                                ->columns(3)
                                ->columnSpanFull()
                                ->addActionLabel('Add Funnel Step')
                                ->helperText('Leave empty to use the built-in starter funnel template.'),
                            Forms\Components\TextInput::make('settings.step_window_minutes')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('Optional: future funnel analysis can use this as the maximum time allowed between steps.'),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'conversion_funnel')
                        ->columnSpanFull(),

                    Section::make('Acquisition Settings')
                        ->schema([
                            Forms\Components\Select::make('settings.attribution_model')
                                ->options(SavedSignalReportDefinition::attributionModelOptions())
                                ->default(SavedSignalReportDefinition::ATTRIBUTION_MODEL_EVENT)
                                ->required(),
                            Forms\Components\TextInput::make('settings.conversion_event_name')
                                ->default(SavedSignalReportDefinition::conversionEventName(null))
                                ->required()
                                ->helperText('Defaults to the configured primary outcome event.')
                                ->maxLength(255),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'acquisition')
                        ->columns(2)
                        ->columnSpanFull(),

                    Section::make('Journey Settings')
                        ->schema([
                            Forms\Components\Select::make('settings.breakdown_dimension')
                                ->label('Breakdown Dimension')
                                ->options(SavedSignalReportDefinition::journeyBreakdownDimensionOptions())
                                ->default('path_pair')
                                ->required(),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'journeys')
                        ->columnSpanFull(),

                    Section::make('Content Settings')
                        ->schema([
                            Forms\Components\Select::make('settings.breakdown_dimension')
                                ->label('Breakdown Dimension')
                                ->options(SavedSignalReportDefinition::contentBreakdownDimensionOptions())
                                ->default('path')
                                ->required(),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'content_performance')
                        ->columnSpanFull(),

                    Section::make('Retention Settings')
                        ->schema([
                            Forms\Components\Repeater::make('settings.retention_windows')
                                ->schema([
                                    Forms\Components\TextInput::make('days')
                                        ->label('Days')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),
                                ])
                                ->columnSpanFull()
                                ->default([
                                    ['days' => 7],
                                    ['days' => 30],
                                ])
                                ->addActionLabel('Add Retention Window')
                                ->helperText('Leave empty to use the default 7-day and 30-day retention windows.'),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'retention')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('report_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('segment.name')
                    ->label('Segment')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_shared')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_shared'),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('report_type')
                    ->options(SavedSignalReportDefinition::reportTypeOptions()),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSavedSignalReports::route('/'),
            'create' => Pages\CreateSavedSignalReport::route('/create'),
            'edit' => Pages\EditSavedSignalReport::route('/{record}/edit'),
        ];
    }
}
