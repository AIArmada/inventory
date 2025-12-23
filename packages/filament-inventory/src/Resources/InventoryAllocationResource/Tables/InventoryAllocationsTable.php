<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryAllocationResource\Tables;

use AIArmada\FilamentInventory\Actions\ReleaseAllocationAction;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Facades\InventoryAllocation as InventoryAllocationFacade;
use AIArmada\Inventory\Models\InventoryAllocation;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class InventoryAllocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('inventoryable_type')
                    ->label('Product Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('inventoryable_id')
                    ->label('Product ID')
                    ->limit(8)
                    ->tooltip(fn (string $state): string => $state)
                    ->copyable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('cart_id')
                    ->label('Cart ID')
                    ->limit(8)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (InventoryAllocation $record): string => $record->isExpired() ? 'danger' : 'success'),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (InventoryAllocation $record): string => $record->isExpired() ? 'Expired' : 'Active')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Expired' ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship(
                        name: 'location',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->where('expires_at', '>', now()),
                            'expired' => $query->where('expires_at', '<', now()),
                            default => $query,
                        };
                    }),

                Filter::make('expiring_soon')
                    ->label('Expiring in 15 minutes')
                    ->query(
                        fn (Builder $query): Builder => $query
                            ->where('expires_at', '>', now())
                            ->where('expires_at', '<', now()->addMinutes(15))
                    ),
            ])
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                ReleaseAllocationAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('release_selected')
                        ->label('Release Selected')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Release Selected Allocations')
                        ->modalDescription('This will release all selected allocations back to available stock.')
                        ->action(function (Collection $records): void {
                            $totalReleased = 0;
                            $count = $records->count();

                            DB::transaction(function () use ($records, &$totalReleased): void {
                                /** @var InventoryAllocation $record */
                                foreach ($records as $record) {
                                    $totalReleased += InventoryAllocationFacade::releaseAllocation($record);
                                }
                            });

                            if ($totalReleased <= 0) {
                                Notification::make()
                                    ->title('Release Failed')
                                    ->body('No allocations were released. They may be outside the current owner context or already released.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Allocations Released')
                                ->body("Released {$totalReleased} units from {$count} allocations.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('expires_at', 'asc')
            ->paginated([25, 50, 100])
            ->striped();
    }
}
