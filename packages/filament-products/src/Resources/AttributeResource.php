<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\AttributeResource\Pages;
use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Models\Attribute;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string | UnitEnum | null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 40;

    public static function getEloquentQuery(): Builder
    {
        return Attribute::query()
            ->forOwner();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-products::resources.attributes.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament-products::resources.attributes.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-products::resources.attributes.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament-products::resources.attributes.sections.basic'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('filament-products::resources.attributes.fields.code'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->alphaDash()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', $state ? Str::slug($state, '_') : '')),

                        Forms\Components\TextInput::make('name')
                            ->label(__('filament-products::resources.attributes.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label(__('filament-products::resources.attributes.fields.description'))
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\Select::make('type')
                            ->label(__('filament-products::resources.attributes.fields.type'))
                            ->options(
                                collect(AttributeType::cases())
                                    ->mapWithKeys(fn (AttributeType $type) => [$type->value => $type->label()])
                            )
                            ->required()
                            ->live()
                            ->native(false),

                        Forms\Components\Select::make('groups')
                            ->label(__('filament-products::resources.attributes.fields.groups'))
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
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->maxLength(100)
                                    ->alphaDash(),
                            ]),

                        Forms\Components\TextInput::make('position')
                            ->label(__('filament-products::resources.attributes.fields.position'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2),

                Section::make(__('filament-products::resources.attributes.sections.options'))
                    ->schema([
                        Forms\Components\Repeater::make('options')
                            ->label(__('filament-products::resources.attributes.fields.options'))
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label(__('filament-products::resources.attributes.fields.option_value'))
                                    ->required(),
                                Forms\Components\TextInput::make('label')
                                    ->label(__('filament-products::resources.attributes.fields.option_label'))
                                    ->required(),
                                Forms\Components\TextInput::make('position')
                                    ->label(__('filament-products::resources.attributes.fields.position'))
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['select', 'multiselect'], true)),

                Section::make(__('filament-products::resources.attributes.sections.validation'))
                    ->schema([
                        Forms\Components\Toggle::make('is_required')
                            ->label(__('filament-products::resources.attributes.fields.is_required'))
                            ->default(false),

                        Forms\Components\KeyValue::make('validation_rules')
                            ->label(__('filament-products::resources.attributes.fields.validation_rules'))
                            ->keyLabel(__('filament-products::resources.attributes.fields.rule'))
                            ->valueLabel(__('filament-products::resources.attributes.fields.value'))
                            ->addActionLabel(__('filament-products::resources.attributes.fields.add_rule'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make(__('filament-products::resources.attributes.sections.visibility'))
                    ->schema([
                        Forms\Components\Toggle::make('is_filterable')
                            ->label(__('filament-products::resources.attributes.fields.is_filterable'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_filterable_help'))
                            ->default(false),

                        Forms\Components\Toggle::make('is_searchable')
                            ->label(__('filament-products::resources.attributes.fields.is_searchable'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_searchable_help'))
                            ->default(false),

                        Forms\Components\Toggle::make('is_comparable')
                            ->label(__('filament-products::resources.attributes.fields.is_comparable'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_comparable_help'))
                            ->default(false),

                        Forms\Components\Toggle::make('is_visible_on_front')
                            ->label(__('filament-products::resources.attributes.fields.is_visible_on_front'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_visible_on_front_help'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_visible_in_admin')
                            ->label(__('filament-products::resources.attributes.fields.is_visible_in_admin'))
                            ->helperText(__('filament-products::resources.attributes.fields.is_visible_in_admin_help'))
                            ->default(true),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('filament-products::resources.attributes.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-products::resources.attributes.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('filament-products::resources.attributes.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (AttributeType $state): string => $state->label())
                    ->color(fn (AttributeType $state): string => $state->color())
                    ->icon(fn (AttributeType $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('groups.name')
                    ->label(__('filament-products::resources.attributes.fields.groups'))
                    ->badge()
                    ->separator(',')
                    ->limitList(2)
                    ->expandableLimitedList(),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('filament-products::resources.attributes.fields.is_required'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_filterable')
                    ->label(__('filament-products::resources.attributes.fields.is_filterable'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_searchable')
                    ->label(__('filament-products::resources.attributes.fields.is_searchable'))
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('position')
                    ->label(__('filament-products::resources.attributes.fields.position'))
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-products::resources.attributes.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('filament-products::resources.attributes.fields.type'))
                    ->options(
                        collect(AttributeType::cases())
                            ->mapWithKeys(fn (AttributeType $type) => [$type->value => $type->label()])
                    ),

                Tables\Filters\SelectFilter::make('groups')
                    ->label(__('filament-products::resources.attributes.fields.groups'))
                    ->relationship(
                        'groups',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            /** @var Builder<\AIArmada\Products\Models\AttributeGroup> $query */
                            return $query->forOwner();
                        }
                    )
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_required')
                    ->label(__('filament-products::resources.attributes.fields.is_required')),

                Tables\Filters\TernaryFilter::make('is_filterable')
                    ->label(__('filament-products::resources.attributes.fields.is_filterable')),

                Tables\Filters\TernaryFilter::make('is_searchable')
                    ->label(__('filament-products::resources.attributes.fields.is_searchable')),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListAttributes::route('/'),
            'create' => Pages\CreateAttribute::route('/create'),
            'edit' => Pages\EditAttribute::route('/{record}/edit'),
        ];
    }
}
