<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\CollectionResource\Pages;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | UnitEnum | null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return Collection::query()
            ->forOwner()
            ->withCount(['products']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->where('is_visible', true)->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Collection Information')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Collection Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
                                    ),

                                TextInput::make('slug')
                                    ->label('URL Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                MarkdownEditor::make('description')
                                    ->label('Description')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Collection Type')
                            ->schema([
                                Radio::make('type')
                                    ->label('Type')
                                    ->options([
                                        'manual' => 'Manual - Add products individually',
                                        'automatic' => 'Automatic - Products match conditions',
                                    ])
                                    ->default('manual')
                                    ->required()
                                    ->live(),

                                Repeater::make('conditions')
                                    ->label('Conditions')
                                    ->schema([
                                        Select::make('field')
                                            ->label('Field')
                                            ->options([
                                                'price_min' => 'Minimum Price',
                                                'price_max' => 'Maximum Price',
                                                'type' => 'Product Type',
                                                'category' => 'Category',
                                                'tag' => 'Tag',
                                                'is_featured' => 'Featured',
                                            ])
                                            ->required()
                                            ->live(),

                                        TextInput::make('value')
                                            ->label('Value')
                                            ->required()
                                            ->visible(fn (Get $get) => in_array($get('field'), ['price_min', 'price_max', 'tag'])),

                                        Select::make('value')
                                            ->label('Value')
                                            ->options([
                                                'simple' => 'Simple',
                                                'configurable' => 'Configurable',
                                                'bundle' => 'Bundle',
                                                'digital' => 'Digital',
                                                'subscription' => 'Subscription',
                                            ])
                                            ->visible(fn (Get $get) => $get('field') === 'type'),

                                        Select::make('value')
                                            ->label('Category')
                                            ->options(fn () => Category::query()->forOwner()->pluck('name', 'id'))
                                            ->searchable()
                                            ->visible(fn (Get $get) => $get('field') === 'category'),

                                        Toggle::make('value')
                                            ->label('Is Featured')
                                            ->visible(fn (Get $get) => $get('field') === 'is_featured'),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add Condition')
                                    ->visible(fn (Get $get) => $get('type') === 'automatic'),
                            ]),

                        Section::make('Scheduling')
                            ->schema([
                                DateTimePicker::make('published_at')
                                    ->label('Publish At')
                                    ->helperText('Leave blank to publish immediately'),

                                DateTimePicker::make('unpublished_at')
                                    ->label('Unpublish At')
                                    ->helperText('Leave blank to keep published'),
                            ])
                            ->columns(2)
                            ->collapsible(),

                        Section::make('SEO')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70),

                                Textarea::make('meta_description')
                                    ->label('Meta Description')
                                    ->rows(3)
                                    ->maxLength(160),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Display')
                            ->schema([
                                TextInput::make('position')
                                    ->label('Position')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_visible')
                                    ->label('Visible')
                                    ->default(true),

                                Toggle::make('is_featured')
                                    ->label('Featured Collection'),
                            ]),

                        Section::make('Media')
                            ->schema([
                                FileUpload::make('hero_image')
                                    ->label('Hero Image')
                                    ->image()
                                    ->imageEditor(),

                                FileUpload::make('banner_image')
                                    ->label('Banner Image')
                                    ->image()
                                    ->imageEditor(),
                            ]),

                        Section::make('Products')
                            ->schema([
                                Select::make('products')
                                    ->label('Products')
                                    ->relationship(
                                        'products',
                                        'name',
                                        modifyQueryUsing: function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
                                            /** @var \Illuminate\Database\Eloquent\Builder<\AIArmada\Products\Models\Product> $query */
                                            return $query->forOwner();
                                        }
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('For manual collections only'),
                            ])
                            ->visible(fn (Get $get) => $get('type') !== 'automatic'),
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
                    ->label('Collection')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->slug),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => $state === 'automatic' ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Immediate')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'manual' => 'Manual',
                        'automatic' => 'Automatic',
                    ]),

                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('rebuild')
                    ->label('Rebuild')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->isAutomatic())
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->rebuildProductList();

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Collection Rebuilt')
                            ->body('Products have been re-matched based on conditions.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Collection Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')
                            ->copyable(),
                        TextEntry::make('type')
                            ->badge(),
                        TextEntry::make('products_count')
                            ->label('Products'),
                    ])
                    ->columns(4),

                Section::make('Scheduling')
                    ->schema([
                        TextEntry::make('published_at')
                            ->label('Publish At')
                            ->dateTime()
                            ->placeholder('Immediate'),
                        TextEntry::make('unpublished_at')
                            ->label('Unpublish At')
                            ->dateTime()
                            ->placeholder('Never'),
                        IconEntry::make('is_currently_published')
                            ->label('Currently Published')
                            ->getStateUsing(fn ($record) => $record->isPublished())
                            ->boolean(),
                    ])
                    ->columns(3),
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
            'index' => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'view' => Pages\ViewCollection::route('/{record}'),
            'edit' => Pages\EditCollection::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }
}
