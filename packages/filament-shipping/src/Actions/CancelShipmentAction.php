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

class CancelShipmentAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel Shipment')
            ->modalDescription('Are you sure you want to cancel this shipment? This action cannot be undone.')
            ->visible(fn (Shipment $record): bool => in_array($record->status, [
                ShipmentStatus::Draft,
                ShipmentStatus::Pending,
                ShipmentStatus::Shipped,
            ], true))
            ->action(function (Shipment $record): void {
                try {
                    $shipmentService = app(ShipmentService::class);
                    $shipmentService->cancel($record);

                    Notification::make()
                        ->title('Shipment Cancelled')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Cancellation Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'cancel');
    }
}
