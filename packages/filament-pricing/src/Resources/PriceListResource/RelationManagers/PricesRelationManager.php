<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Prices';

    private function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('priceable_type')
                    ->label('Type')
                    ->options([
                        \AIArmada\Products\Models\Product::class => 'Product',
                        \AIArmada\Products\Models\Variant::class => 'Variant',
                    ])
                    ->required()
                    ->live()
                    ->default(\AIArmada\Products\Models\Product::class),

                Forms\Components\Select::make('priceable_id')
                    ->label('Product/Variant')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(function (string $search, Forms\Get $get): array {
                        $type = $get('priceable_type');

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
                    ->getOptionLabelUsing(function ($value, Forms\Get $get): ?string {
                        if ($value === null) {
                            return null;
                        }

                        $type = $get('priceable_type');

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

                        $record = $query
                            ->whereKey($value)
                            ->first();

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

                Forms\Components\TextInput::make('amount')
                    ->label('Price (cents)')
                    ->numeric()
                    ->required()
                    ->helperText('Enter price in cents (e.g., 1000 = RM 10.00)'),

                Forms\Components\TextInput::make('compare_amount')
                    ->label('Compare Price (cents)')
                    ->numeric()
                    ->helperText('Original price for strike-through display'),

                Forms\Components\TextInput::make('min_quantity')
                    ->label('Minimum Quantity')
                    ->numeric()
                    ->default(1),

                Forms\Components\Select::make('currency')
                    ->options([
                        'MYR' => 'MYR',
                        'USD' => 'USD',
                        'SGD' => 'SGD',
                    ])
                    ->default('MYR'),

                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Start Date'),

                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('End Date'),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('priceable_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => class_basename($state)),

                Tables\Columns\TextColumn::make('priceable.name')
                    ->label('Product')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Price')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('compare_amount')
                    ->label('Compare')
                    ->money('MYR', divideBy: 100)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('min_quantity')
                    ->label('Min Qty')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y')
                    ->placeholder('Always'),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y')
                    ->placeholder('Never'),
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
