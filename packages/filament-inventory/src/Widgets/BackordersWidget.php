<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryBackorder;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

final class BackordersWidget extends TableWidget
{
    protected static ?int $sort = 50;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Open Backorders';

    public static function canView(): bool
    {
        return config('filament-inventory.features.backorders_widget', true);
    }

    public function table(Table $table): Table
    {
        $query = InventoryBackorder::query()
            ->open()
            ->byPriority()
            ->with(['location'])
            ->limit(10);

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::resolveOwner() === null;

            $query->where(function (Builder $builder) use ($includeNullLocation): void {
                $builder->whereHas('location', fn (Builder $locationQuery): Builder => InventoryOwnerScope::applyToLocationQuery($locationQuery));

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('inventoryable_type')
                    ->label('Product Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),

                TextColumn::make('order_id')
                    ->label('Order')
                    ->placeholder('No order')
                    ->searchable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('Any location'),

                TextColumn::make('quantity_requested')
                    ->label('Requested')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('quantity_fulfilled')
                    ->label('Fulfilled')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('quantityRemaining')
                    ->label('Remaining')
                    ->state(fn (InventoryBackorder $record): int => $record->quantityRemaining())
                    ->numeric()
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('promised_at')
                    ->label('Promised')
                    ->date()
                    ->placeholder('Not set')
                    ->color(fn (?InventoryBackorder $record): string => match (true) {
                        $record?->promised_at === null => 'gray',
                        $record->isOverdue() => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (InventoryBackorder $record): string => $record->priority->color()),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InventoryBackorder $record): string => $record->status->color()),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (): bool => Route::has('filament.admin.resources.inventory-backorders.view'))
                    ->url(fn (InventoryBackorder $record): string => route('filament.admin.resources.inventory-backorders.view', $record)),
            ])
            ->emptyStateHeading('No Open Backorders')
            ->emptyStateDescription('All orders are fully stocked.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
