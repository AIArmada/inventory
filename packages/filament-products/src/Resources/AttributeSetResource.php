<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages;
use AIArmada\Products\Models\AttributeSet;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class AttributeSetResource extends Resource
{
    protected static ?string $model = AttributeSet::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string | UnitEnum | null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 42;

    protected static ?string $navigationParentItem = 'Attributes';

    public static function getEloquentQuery(): Builder
    {
        return AttributeSet::query()
            ->forOwner();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-products::resources.attribute_sets.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament-products::resources.attribute_sets.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-products::resources.attribute_sets.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament-products::resources.attribute_sets.sections.basic'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('filament-products::resources.attribute_sets.fields.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->alphaDash()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', $state ? Str::slug($state, '_') : '')),

                        Forms\Components\TextInput::make('name')
                            ->label(__('filament-products::resources.attribute_sets.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label(__('filament-products::resources.attribute_sets.fields.description'))
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\Toggle::make('is_default')
                            ->label(__('filament-products::resources.attribute_sets.fields.is_default'))
                            ->helperText(__('filament-products::resources.attribute_sets.fields.is_default_help'))
                            ->default(false),
                    ])
                    ->columns(2),

                Section::make(__('filament-products::resources.attribute_sets.sections.attributes'))
                    ->schema([
                        Forms\Components\Select::make('setAttributes')
                            ->label(__('filament-products::resources.attribute_sets.fields.attributes'))
                            ->multiple()
                            ->relationship(
                                'setAttributes',
                                'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    /** @var Builder<\AIArmada\Products\Models\Attribute> $query */
                                    return $query->forOwner();
                                }
                            )
                            ->preload()
                            ->searchable(),
                    ]),

                Section::make(__('filament-products::resources.attribute_sets.sections.groups'))
                    ->schema([
                        Forms\Components\Select::make('groups')
                            ->label(__('filament-products::resources.attribute_sets.fields.groups'))
                            ->multiple()
                            ->relationship(
                                'groups',
                                'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    /** @var Builder<\AIArmada\Products\Models\AttributeGroup> $query */
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
                    ->label(__('filament-products::resources.attribute_sets.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-products::resources.attribute_sets.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('set_attributes_count')
                    ->label(__('filament-products::resources.attribute_sets.fields.attributes_count'))
                    ->counts('setAttributes')
                    ->badge()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('groups_count')
                    ->label(__('filament-products::resources.attribute_sets.fields.groups_count'))
                    ->counts('groups')
                    ->badge()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('filament-products::resources.attribute_sets.fields.is_default'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-products::resources.attribute_sets.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label(__('filament-products::resources.attribute_sets.fields.is_default')),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('setDefault')
                    ->label(__('filament-products::resources.attribute_sets.actions.set_default'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (AttributeSet $record) => $record->setAsDefault())
                    ->visible(fn (AttributeSet $record): bool => ! $record->is_default),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListAttributeSets::route('/'),
            'create' => Pages\CreateAttributeSet::route('/create'),
            'edit' => Pages\EditAttributeSet::route('/{record}/edit'),
        ];
    }
}
