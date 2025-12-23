<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Pages;

use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BulkEditProducts extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-pencil-square';

    protected string $view = 'filament-products::pages.bulk-edit-products';

    protected static string | UnitEnum | null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 98;

    protected static ?string $title = 'Bulk Edit';

    /**
     * Bulk editing should never mutate global records when a tenant owner is resolved.
     *
     * @return Builder<Product>
     */
    private function getOwnerOnlyProductsQuery(): Builder
    {
        $owner = OwnerScope::resolveOwner();

        return Product::query()->forOwner($owner, false);
    }

    /**
     * @param  Builder<\AIArmada\Products\Models\Category>  $query
     * @return Builder<\AIArmada\Products\Models\Category>
     */
    private function scopeCategoriesQuery(Builder $query): Builder
    {
        $owner = OwnerScope::resolveOwner();

        return $query->forOwner($owner, false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getOwnerOnlyProductsQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->sku),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color('info'),

                Tables\Columns\TextColumn::make('price')
                    ->money(fn (Product $record): string => $record->currency, divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ProductStatus::class),

                Tables\Filters\SelectFilter::make('type')
                    ->options(\AIArmada\Products\Enums\ProductType::class),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('update_price')
                        ->label('Update Price')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('success')
                        ->form([
                            Radio::make('price_action')
                                ->label('Action')
                                ->options([
                                    'set' => 'Set to specific value',
                                    'increase_percent' => 'Increase by percentage',
                                    'decrease_percent' => 'Decrease by percentage',
                                    'increase_amount' => 'Increase by amount',
                                    'decrease_amount' => 'Decrease by amount',
                                ])
                                ->required()
                                ->live()
                                ->default('set'),

                            TextInput::make('value')
                                ->label(function (Get $get) {
                                    return match ($get('price_action')) {
                                        'set' => 'New Price (RM)',
                                        'increase_percent', 'decrease_percent' => 'Percentage (%)',
                                        'increase_amount', 'decrease_amount' => 'Amount (RM)',
                                        default => 'Value',
                                    };
                                })
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $product) {
                                $currentPrice = $product->price / 100;

                                $newPrice = match ($data['price_action']) {
                                    'set' => $data['value'],
                                    'increase_percent' => $currentPrice * (1 + $data['value'] / 100),
                                    'decrease_percent' => $currentPrice * (1 - $data['value'] / 100),
                                    'increase_amount' => $currentPrice + $data['value'],
                                    'decrease_amount' => $currentPrice - $data['value'],
                                    default => $currentPrice,
                                };

                                $newPrice = max(0, $newPrice);

                                $product->update(['price' => (int) round($newPrice * 100)]);
                            }

                            Notification::make()
                                ->title('Prices updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\BulkAction::make('update_status')
                        ->label('Change Status')
                        ->icon('heroicon-o-flag')
                        ->form([
                            Select::make('status')
                                ->label('New Status')
                                ->options(ProductStatus::class)
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $product) {
                                $product->update(['status' => $data['status']]);
                            }

                            Notification::make()
                                ->title('Status updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\BulkAction::make('update_visibility')
                        ->label('Change Visibility')
                        ->icon('heroicon-o-eye')
                        ->form([
                            Select::make('visibility')
                                ->label('New Visibility')
                                ->options(\AIArmada\Products\Enums\ProductVisibility::class)
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $product) {
                                $product->update(['visibility' => $data['visibility']]);
                            }

                            Notification::make()
                                ->title('Visibility updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    \Filament\Actions\BulkAction::make('assign_categories')
                        ->label('Assign Categories')
                        ->icon('heroicon-o-folder')
                        ->color('info')
                        ->form([
                            Select::make('categories')
                                ->label('Categories')
                                ->relationship(
                                    'categories',
                                    'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => $this->scopeCategoriesQuery($query)
                                )
                                ->multiple()
                                ->searchable()
                                ->preload(),

                            Radio::make('mode')
                                ->label('Mode')
                                ->options([
                                    'replace' => 'Replace existing categories',
                                    'add' => 'Add to existing categories',
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            /** @var array<int, string> $categories */
                            $categories = $data['categories'] ?? [];
                            $categories = OwnerScope::ensureAllowed('categories', Category::class, $categories);

                            foreach ($records as $product) {
                                if ($data['mode'] === 'replace') {
                                    $product->categories()->sync($categories);
                                } else {
                                    $product->categories()->syncWithoutDetaching($categories);
                                }
                            }

                            Notification::make()
                                ->title('Categories assigned')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
