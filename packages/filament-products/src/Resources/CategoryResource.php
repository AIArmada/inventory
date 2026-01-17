<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources;

use AIArmada\FilamentProducts\Resources\CategoryResource\Pages;
use AIArmada\Products\Models\Category;
use BackedEnum;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-folder';

    protected static string | UnitEnum | null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return Category::query()
            ->forOwner()
            ->withCount(['products', 'children']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Category Information')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Category Name')
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

                                Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->relationship(
                                        'parent',
                                        'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            /** @var Builder<\AIArmada\Products\Models\Category> $query */
                                            return $query->forOwner();
                                        }
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('None (Root Category)')
                                    ->helperText('Leave blank to make this a root category'),

                                MarkdownEditor::make('description')
                                    ->label('Description')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('SEO')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70)
                                    ->helperText('Leave blank to use category name'),

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
                                    ->default(0)
                                    ->helperText('Lower numbers appear first'),

                                Toggle::make('is_visible')
                                    ->label('Visible')
                                    ->default(true)
                                    ->helperText('Show in navigation and listing'),

                                Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->helperText('Highlight on homepage'),
                            ]),

                        Section::make('Media')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('hero')
                                    ->label('Hero Image')
                                    ->collection('hero')
                                    ->image()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.hero.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.hero.limit', 1)),

                                SpatieMediaLibraryFileUpload::make('icon')
                                    ->label('Icon Image')
                                    ->collection('icon')
                                    ->image()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.icon.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.icon.limit', 1)),

                                SpatieMediaLibraryFileUpload::make('banner')
                                    ->label('Banner Image')
                                    ->collection('banner')
                                    ->image()
                                    ->imageEditor()
                                    ->acceptedFileTypes(config('products.media.collections.banner.mimes', []))
                                    ->maxFiles((int) config('products.media.collections.banner.limit', 1)),
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
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $depth = $record->getDepth();
                        $prefix = str_repeat('— ', $depth);

                        return $prefix . $record->name;
                    })
                    ->description(fn ($record) => $record->slug),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Root')
                    ->toggleable(),

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

                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship(
                        'parent',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            /** @var Builder<\AIArmada\Products\Models\Category> $query */
                            return $query->forOwner();
                        }
                    )
                    ->placeholder('All')
                    ->options(fn () => ['0' => 'Root Categories'] + Category::query()->forOwner()->whereNull('parent_id')->pluck('name', 'id')->toArray()),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('add_child')
                    ->label('Add Child')
                    ->icon('heroicon-o-plus')
                    ->url(fn ($record) => static::getUrl('create', ['parent' => $record->id])),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('show')
                        ->label('Make Visible')
                        ->icon('heroicon-o-eye')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_visible' => true])
                        ),
                    \Filament\Actions\BulkAction::make('hide')
                        ->label('Make Hidden')
                        ->icon('heroicon-o-eye-slash')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_visible' => false])
                        ),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Category Details')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('slug')
                            ->label('Slug')
                            ->copyable(),
                        TextEntry::make('parent.name')
                            ->label('Parent')
                            ->placeholder('Root Category'),
                        TextEntry::make('full_path')
                            ->label('Full Path')
                            ->getStateUsing(fn ($record) => $record->getFullPath()),
                    ])
                    ->columns(2),

                Section::make('Statistics')
                    ->schema([
                        TextEntry::make('products_count')
                            ->label('Direct Products'),
                        TextEntry::make('all_products_count')
                            ->label('All Products (including children)')
                            ->getStateUsing(fn ($record) => $record->getProductCount(true)),
                        TextEntry::make('children_count')
                            ->label('Child Categories'),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }
}
