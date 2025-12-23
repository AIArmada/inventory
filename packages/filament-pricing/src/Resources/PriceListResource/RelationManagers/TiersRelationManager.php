<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';

    protected static ?string $title = 'Price Tiers';

    protected static ?string $recordTitleAttribute = 'min_quantity';

    private function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Tier Configuration')
                    ->schema([
                        Forms\Components\Select::make('tierable_type')
                            ->label('Apply To')
                            ->options([
                                \AIArmada\Products\Models\Product::class => 'Product',
                                \AIArmada\Products\Models\Variant::class => 'Variant',
                            ])
                            ->required()
                            ->live()
                            ->default(\AIArmada\Products\Models\Product::class),

                        Forms\Components\Select::make('tierable_id')
                            ->label('Product/Variant')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(function (string $search, Get $get): array {
                                $type = $get('tierable_type');

                                $owner = $this->resolveOwner();

                                if ($type === \AIArmada\Products\Models\Product::class) {
                                    return \AIArmada\Products\Models\Product::query()
                                        /** @phpstan-ignore-next-line */
                                        ->forOwner($owner)
                                        ->where('name', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                }

                                if ($type === \AIArmada\Products\Models\Variant::class) {
                                    return \AIArmada\Products\Models\Variant::query()
                                        ->with('product')
                                        ->whereHas('product', function (Builder $query) use ($owner): void {
                                            /** @phpstan-ignore-next-line */
                                            $query->forOwner($owner);
                                        })
                                        ->where(function (Builder $query) use ($search): void {
                                            $query->where('sku', 'like', "%{$search}%")
                                                ->orWhereHas('product', fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%"));
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn ($v): array => [$v->id => $v->product->name . ' - ' . $v->sku])
                                        ->toArray();
                                }

                                return [];
                            })
                            ->getOptionLabelUsing(function ($value, Get $get): ?string {
                                if ($value === null) {
                                    return null;
                                }

                                $type = $get('tierable_type');

                                $owner = $this->resolveOwner();

                                if (! is_string($type) || ! class_exists($type) || ! is_a($type, Model::class, true)) {
                                    return null;
                                }

                                /** @var Builder<Model> $query */
                                $query = $type::query();

                                if (method_exists($type, 'scopeForOwner')) {
                                    /** @phpstan-ignore-next-line */
                                    $query = $query->forOwner($owner);
                                }

                                $record = $query->whereKey($value)->first();

                                if (! $record) {
                                    return null;
                                }

                                if ($record instanceof \AIArmada\Products\Models\Variant) {
                                    $record->loadMissing('product');

                                    return $record->product->name . ' - ' . $record->sku;
                                }

                                return (string) ($record->name ?? $record->sku ?? $record->getKey());
                            })
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('min_quantity')
                                    ->label('Minimum Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1),

                                Forms\Components\TextInput::make('max_quantity')
                                    ->label('Maximum Quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable(),

                                Forms\Components\Placeholder::make('range_display')
                                    ->label('Range')
                                    ->content(function (Get $get) {
                                        $min = $get('min_quantity') ?? 1;
                                        $max = $get('max_quantity');

                                        return $max ? "{$min} - {$max}" : "{$min}+";
                                    }),
                            ]),
                    ])
                    ->columns(2),

                Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (cents)')
                            ->numeric()
                            ->required(),

                        Forms\Components\Select::make('discount_type')
                            ->label('Discount Type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed' => 'Fixed (cents)',
                            ])
                            ->nullable(),

                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->numeric()
                            ->nullable()
                            ->helperText('Optional: describes the discount used to compute the tier amount.'),

                        Forms\Components\Select::make('currency')
                            ->options([
                                'MYR' => 'MYR',
                                'USD' => 'USD',
                                'SGD' => 'SGD',
                            ])
                            ->default('MYR'),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('min_quantity')
            ->columns([
                Tables\Columns\TextColumn::make('tierable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),

                Tables\Columns\TextColumn::make('tierable_label')
                    ->label('Item')
                    ->state(function ($record): string {
                        $record->loadMissing('tierable');

                        if ($record->tierable_type === \AIArmada\Products\Models\Variant::class) {
                            $record->tierable?->loadMissing('product');

                            return ($record->tierable?->product?->name ?? 'Variant') . ' - ' . ($record->tierable?->sku ?? $record->tierable_id);
                        }

                        return (string) ($record->tierable?->name ?? $record->tierable_id);
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('quantity_range')
                    ->label('Quantity Range')
                    ->state(function ($record): string {
                        return $record->max_quantity
                            ? "{$record->min_quantity} - {$record->max_quantity}"
                            : "{$record->min_quantity}+";
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('pricing_type')
                    ->label('Type')
                    ->state(function ($record): string {
                        if ($record->discount_type === 'percentage') {
                            return 'Discount (%)';
                        }

                        if ($record->discount_type === 'fixed') {
                            return 'Discount (fixed)';
                        }

                        return 'Fixed Price';
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Discount')
                    ->state(function ($record): ?string {
                        if ($record->discount_type === 'percentage' && $record->discount_value !== null) {
                            return (string) $record->discount_value . '%';
                        }

                        if ($record->discount_type === 'fixed' && $record->discount_value !== null) {
                            return 'RM ' . number_format($record->discount_value / 100, 2);
                        }

                        return null;
                    })
                    ->placeholder('-'),
            ])
            ->defaultSort('min_quantity', 'asc')
            ->filters([
                //
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
