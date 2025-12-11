<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\CollectionResource\Pages;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_visible', true)->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Collection Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Collection Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
                                    ),

                                Forms\Components\TextInput::make('slug')
                                    ->label('URL Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\MarkdownEditor::make('description')
                                    ->label('Description')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Collection Type')
                            ->schema([
                                Forms\Components\Radio::make('type')
                                    ->label('Type')
                                    ->options([
                                        'manual' => 'Manual - Add products individually',
                                        'automatic' => 'Automatic - Products match conditions',
                                    ])
                                    ->default('manual')
                                    ->required()
                                    ->live(),

                                Forms\Components\Repeater::make('conditions')
                                    ->label('Conditions')
                                    ->schema([
                                        Forms\Components\Select::make('field')
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

                                        Forms\Components\TextInput::make('value')
                                            ->label('Value')
                                            ->required()
                                            ->visible(fn (Forms\Get $get) => in_array($get('field'), ['price_min', 'price_max', 'tag'])),

                                        Forms\Components\Select::make('value')
                                            ->label('Value')
                                            ->options([
                                                'simple' => 'Simple',
                                                'configurable' => 'Configurable',
                                                'bundle' => 'Bundle',
                                                'digital' => 'Digital',
                                                'subscription' => 'Subscription',
                                            ])
                                            ->visible(fn (Forms\Get $get) => $get('field') === 'type'),

                                        Forms\Components\Select::make('value')
                                            ->label('Category')
                                            ->options(fn () => Category::pluck('name', 'id'))
                                            ->searchable()
                                            ->visible(fn (Forms\Get $get) => $get('field') === 'category'),

                                        Forms\Components\Toggle::make('value')
                                            ->label('Is Featured')
                                            ->visible(fn (Forms\Get $get) => $get('field') === 'is_featured'),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add Condition')
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'automatic'),
                            ]),

                        Forms\Components\Section::make('Scheduling')
                            ->schema([
                                Forms\Components\DateTimePicker::make('published_at')
                                    ->label('Publish At')
                                    ->helperText('Leave blank to publish immediately'),

                                Forms\Components\DateTimePicker::make('unpublished_at')
                                    ->label('Unpublish At')
                                    ->helperText('Leave blank to keep published'),
                            ])
                            ->columns(2)
                            ->collapsible(),

                        Forms\Components\Section::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70),

                                Forms\Components\Textarea::make('meta_description')
                                    ->label('Meta Description')
                                    ->rows(3)
                                    ->maxLength(160),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Display')
                            ->schema([
                                Forms\Components\TextInput::make('position')
                                    ->label('Position')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\Toggle::make('is_visible')
                                    ->label('Visible')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured Collection'),
                            ]),

                        Forms\Components\Section::make('Media')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('hero')
                                    ->collection('hero')
                                    ->label('Hero Image')
                                    ->image()
                                    ->imageEditor()
                                    ->responsiveImages(),

                                SpatieMediaLibraryFileUpload::make('banner')
                                    ->collection('banner')
                                    ->label('Banner Image')
                                    ->image()
                                    ->imageEditor()
                                    ->responsiveImages(),
                            ]),

                        Forms\Components\Section::make('Products')
                            ->schema([
                                Forms\Components\Select::make('products')
                                    ->label('Products')
                                    ->relationship('products', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('For manual collections only'),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('type') !== 'automatic'),
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Collection Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('slug')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('products_count')
                            ->label('Products')
                            ->getStateUsing(fn ($record) => $record->products()->count()),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Scheduling')
                    ->schema([
                        Infolists\Components\TextEntry::make('published_at')
                            ->label('Publish At')
                            ->dateTime()
                            ->placeholder('Immediate'),
                        Infolists\Components\TextEntry::make('unpublished_at')
                            ->label('Unpublish At')
                            ->dateTime()
                            ->placeholder('Never'),
                        Infolists\Components\IconEntry::make('is_currently_published')
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
