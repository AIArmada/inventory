<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages;
use AIArmada\Products\Models\AttributeGroup;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class AttributeGroupResource extends Resource
{
    protected static ?string $model = AttributeGroup::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string | UnitEnum | null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 41;

    protected static ?string $navigationParentItem = 'Attributes';

    public static function getEloquentQuery(): Builder
    {
        return AttributeGroup::query()
            ->forOwner();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-products::resources.attribute_groups.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament-products::resources.attribute_groups.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-products::resources.attribute_groups.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament-products::resources.attribute_groups.sections.basic'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('filament-products::resources.attribute_groups.fields.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->alphaDash()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', $state ? Str::slug($state, '_') : '')),

                        Forms\Components\TextInput::make('name')
                            ->label(__('filament-products::resources.attribute_groups.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label(__('filament-products::resources.attribute_groups.fields.description'))
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\TextInput::make('position')
                            ->label(__('filament-products::resources.attribute_groups.fields.position'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\Toggle::make('is_visible')
                            ->label(__('filament-products::resources.attribute_groups.fields.is_visible'))
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make(__('filament-products::resources.attribute_groups.sections.attributes'))
                    ->schema([
                        Forms\Components\Select::make('attributes')
                            ->label(__('filament-products::resources.attribute_groups.fields.attributes'))
                            ->multiple()
                            ->relationship(
                                'attributes',
                                'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    /** @var Builder<\AIArmada\Products\Models\Attribute> $query */
                                    return $query->forOwner();
                                }
                            )
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('filament-products::resources.attribute_groups.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-products::resources.attribute_groups.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('attributes_count')
                    ->label(__('filament-products::resources.attribute_groups.fields.attributes_count'))
                    ->counts('attributes')
                    ->badge()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label(__('filament-products::resources.attribute_groups.fields.is_visible'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament-products::resources.attribute_groups.fields.position'))
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-products::resources.attribute_groups.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label(__('filament-products::resources.attribute_groups.fields.is_visible')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttributeGroups::route('/'),
            'create' => Pages\CreateAttributeGroup::route('/create'),
            'edit' => Pages\EditAttributeGroup::route('/{record}/edit'),
        ];
    }
}
