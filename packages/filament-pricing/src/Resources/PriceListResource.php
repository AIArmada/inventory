<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPricing\Resources\PriceListResource\Pages;
use AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers;
use AIArmada\Pricing\Models\PriceList;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string | UnitEnum | null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<PriceList>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<PriceList> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('pricing.features.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolveOwner();

        /** @var Builder<PriceList> $scoped */
        $scoped = $query->forOwner(
            $owner,
            (bool) config('pricing.features.owner.include_global', false),
        );

        return $scoped;
    }

    private static function resolveOwner(): ?Model
    {
        if (! (bool) config('pricing.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Price List Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
                                    ),

                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'MYR' => 'MYR - Malaysian Ringgit',
                                        'USD' => 'USD - US Dollar',
                                        'SGD' => 'SGD - Singapore Dollar',
                                    ])
                                    ->default('MYR')
                                    ->required(),

                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Scheduling')
                            ->schema([
                                Forms\Components\DateTimePicker::make('starts_at')
                                    ->label('Start Date'),

                                Forms\Components\DateTimePicker::make('ends_at')
                                    ->label('End Date'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_default')
                                    ->label('Default Price List')
                                    ->helperText('Used when no other price list applies'),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = more priority'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->badge(),

                Tables\Columns\TextColumn::make('prices_count')
                    ->label('Prices')
                    ->counts('prices')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        if (! class_exists('\\AIArmada\\Products\\Models\\Product') || ! class_exists('\\AIArmada\\Products\\Models\\Variant')) {
            return [];
        }

        return [
            RelationManagers\PricesRelationManager::class,
            RelationManagers\TiersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceLists::route('/'),
            'create' => Pages\CreatePriceList::route('/create'),
            'view' => Pages\ViewPriceList::route('/{record}'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }
}
