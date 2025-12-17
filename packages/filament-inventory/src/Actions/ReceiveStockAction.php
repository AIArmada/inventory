<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class ReceiveStockAction
{
    /**
     * Create the receive stock action for a record.
     */
    public static function make(string $name = 'receive_stock'): Action
    {
        return Action::make($name)
            ->label('Receive Stock')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->modalHeading('Receive Inventory')
            ->modalDescription('Record incoming stock from a supplier or other source.')
            ->form([
                Grid::make(2)
                    ->schema([
                        Select::make('location_id')
                            ->label('Receiving Location')
                            ->options(fn () => InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('quantity')
                            ->label('Quantity Received')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1),

                        TextInput::make('purchase_order')
                            ->label('Purchase Order #')
                            ->placeholder('PO-12345')
                            ->maxLength(50),

                        TextInput::make('supplier')
                            ->label('Supplier')
                            ->placeholder('Supplier name...')
                            ->maxLength(100),

                        DatePicker::make('received_at')
                            ->label('Received Date')
                            ->default(now())
                            ->required(),
                    ]),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->placeholder('Quality notes, inspection results, etc...'),
            ])
            ->action(function (Model $record, array $data): void {
                $inventoryService = app(InventoryService::class);

                $reason = null;
                if (isset($data['purchase_order']) || isset($data['supplier'])) {
                    $parts = array_filter([
                        $data['purchase_order'] ?? null,
                        $data['supplier'] ?? null,
                    ]);
                    $reason = implode(' - ', $parts);
                }

                $movement = $inventoryService->receive(
                    model: $record,
                    locationId: $data['location_id'],
                    quantity: (int) $data['quantity'],
                    reason: $reason,
                    note: $data['notes'] ?? null,
                    userId: Auth::id(),
                    occurredAt: $data['received_at'] ?? null,
                );

                Notification::make()
                    ->title('Stock Received')
                    ->body("Received {$data['quantity']} units successfully.")
                    ->success()
                    ->send();
            });
    }
}
