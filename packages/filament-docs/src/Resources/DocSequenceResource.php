<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources;

use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Enums\ResetFrequency;
use AIArmada\Docs\Models\DocSequence;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

final class DocSequenceResource extends Resource
{
    protected static ?string $model = DocSequence::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?string $navigationLabel = 'Sequences';

    protected static ?string $modelLabel = 'Document Sequence';

    protected static ?string $pluralModelLabel = 'Document Sequences';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Sequence Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('doc_type')
                                    ->label('Document Type')
                                    ->options(collect(DocType::cases())
                                        ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                        ->all())
                                    ->required(),

                                TextInput::make('prefix')
                                    ->required()
                                    ->maxLength(20)
                                    ->default('INV')
                                    ->helperText('e.g., INV, QUO, CN'),

                                Select::make('reset_frequency')
                                    ->label('Reset Frequency')
                                    ->options(collect(ResetFrequency::cases())
                                        ->mapWithKeys(fn ($freq) => [$freq->value => $freq->label()])
                                        ->all())
                                    ->default('yearly')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Number Format')
                    ->schema([
                        TextInput::make('format')
                            ->required()
                            ->default('{PREFIX}-{YYMM}-{NUMBER}')
                            ->helperText('Tokens: {PREFIX}, {NUMBER}, {YYYY}, {YY}, {MM}, {DD}, {YYMM}, {YYMMDD}')
                            ->columnSpanFull(),

                        Grid::make(4)
                            ->schema([
                                TextInput::make('start_number')
                                    ->label('Start Number')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1),

                                TextInput::make('increment')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1),

                                TextInput::make('padding')
                                    ->label('Number Padding')
                                    ->numeric()
                                    ->default(6)
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->helperText('Zeros to pad'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),
                    ]),

                Section::make('Preview')
                    ->schema([
                        Text::make('preview')
                            ->content(function ($record): string {
                                if (! $record) {
                                    return 'Save to see preview';
                                }

                                return 'Next number: ' . $record->previewNextNumber();
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('doc_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('prefix')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('format')
                    ->limit(30),

                TextColumn::make('reset_frequency')
                    ->label('Reset')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('doc_type')
                    ->options(collect(DocType::cases())
                        ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                        ->all()),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => DocSequenceResource\Pages\ListDocSequences::route('/'),
            'create' => DocSequenceResource\Pages\CreateDocSequence::route('/create'),
            'edit' => DocSequenceResource\Pages\EditDocSequence::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-docs.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-docs.resources.navigation_sort.sequences', 90);
    }
}
