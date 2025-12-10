<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\ShipmentService;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Throwable;

class ShipAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('ship')
            ->label('Ship')
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Ship Package')
            ->modalDescription('This will create the shipment with the carrier and generate tracking.')
            ->visible(fn (Shipment $record): bool => $record->status === ShipmentStatus::Pending)
            ->action(function (Shipment $record): void {
                try {
                    $shipmentService = app(ShipmentService::class);
                    $shipmentService->ship($record);

                    Notification::make()
                        ->title('Shipment Created')
                        ->body("Tracking: {$record->tracking_number}")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Shipment Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'ship');
    }
}
