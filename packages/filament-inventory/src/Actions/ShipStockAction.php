<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Exceptions\InsufficientStockException;
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

final class ShipStockAction
{
    /**
     * Create the ship stock action for a record.
     */
    public static function make(string $name = 'ship_stock'): Action
    {
        return Action::make($name)
            ->label('Ship Stock')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalHeading('Ship Inventory')
            ->modalDescription('Record outgoing stock for an order or shipment.')
            ->form([
                Grid::make(2)
                    ->schema([
                        Select::make('location_id')
                            ->label('Shipping From')
                            ->options(fn () => InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('quantity')
                            ->label('Quantity to Ship')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1),

                        TextInput::make('order_number')
                            ->label('Order #')
                            ->placeholder('ORD-12345')
                            ->maxLength(50),

                        TextInput::make('customer')
                            ->label('Customer')
                            ->placeholder('Customer name...')
                            ->maxLength(100),

                        TextInput::make('tracking_number')
                            ->label('Tracking #')
                            ->placeholder('Carrier tracking number...')
                            ->maxLength(100),

                        DatePicker::make('shipped_at')
                            ->label('Ship Date')
                            ->default(now())
                            ->required(),
                    ]),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->placeholder('Shipping notes, special instructions, etc...'),
            ])
            ->action(function (Model $record, array $data): void {
                $inventoryService = app(InventoryService::class);

                $reason = null;
                $parts = array_filter([
                    $data['order_number'] ?? null,
                    $data['customer'] ?? null,
                    $data['tracking_number'] ?? null,
                ]);
                if (count($parts) > 0) {
                    $reason = implode(' - ', $parts);
                }

                try {
                    $movement = $inventoryService->ship(
                        model: $record,
                        locationId: $data['location_id'],
                        quantity: (int) $data['quantity'],
                        reason: $reason,
                        note: $data['notes'] ?? null,
                        userId: Auth::id(),
                        occurredAt: $data['shipped_at'] ?? null,
                    );

                    Notification::make()
                        ->title('Stock Shipped')
                        ->body("Shipped {$data['quantity']} units successfully.")
                        ->success()
                        ->send();
                } catch (InsufficientStockException $e) {
                    Notification::make()
                        ->title('Shipment Failed')
                        ->body('Insufficient stock at this location.')
                        ->danger()
                        ->send();
                }
            });
    }
}
