<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class AdjustStockAction
{
    /**
     * Create the adjust stock action for a record.
     */
    public static function make(string $name = 'adjust_stock'): Action
    {
        return Action::make($name)
            ->label('Adjust Stock')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('warning')
            ->modalHeading('Adjust Stock Level')
            ->modalDescription('Make an inventory adjustment for this item.')
            ->form([
                Grid::make(2)
                    ->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->options(fn () => InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('new_quantity')
                            ->label('New Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->helperText('The quantity to set the stock level to'),

                        Select::make('reason')
                            ->label('Reason')
                            ->options([
                                'cycle_count' => 'Cycle Count',
                                'damaged' => 'Damaged',
                                'expired' => 'Expired',
                                'lost' => 'Lost',
                                'found' => 'Found',
                                'correction' => 'Correction',
                                'initial_stock' => 'Initial Stock',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->default('correction'),
                    ]),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->placeholder('Optional notes for this adjustment...'),
            ])
            ->action(function (Model $record, array $data): void {
                $inventoryService = app(InventoryService::class);

                $movement = $inventoryService->adjust(
                    model: $record,
                    locationId: $data['location_id'],
                    newQuantity: (int) $data['new_quantity'],
                    reason: $data['reason'],
                    note: $data['notes'] ?? null,
                    userId: Auth::id(),
                );

                Notification::make()
                    ->title('Stock Adjusted')
                    ->body("Stock level adjusted. Movement ID: {$movement->id}")
                    ->success()
                    ->send();
            });
    }
}
