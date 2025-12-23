<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\BatchRateLimiter;
use AIArmada\Shipping\Services\ShipmentService;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class BulkShipAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('bulk_ship')
            ->label('Ship Selected')
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Ship Selected Packages')
            ->modalDescription('This will create shipments with carriers for all selected pending packages.')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => auth()->user()?->can('shipping.shipments.ship') ?? false)
            ->action(function (Collection $records): void {
                $user = auth()->user();

                if ($user === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to ship shipments.')
                        ->danger()
                        ->send();

                    return;
                }

                $shipmentService = app(ShipmentService::class);

                // Filter to only pending shipments
                $pendingShipments = $records->filter(
                    fn ($record) => $record instanceof Shipment
                        && $record->status === ShipmentStatus::Pending
                        && $user->can('ship', $record)
                );

                if ($pendingShipments->isEmpty()) {
                    Notification::make()
                        ->title('No Pending Shipments')
                        ->body('None of the selected records are pending shipments.')
                        ->warning()
                        ->send();

                    return;
                }

                // Group by carrier for rate limiting
                $byCarrier = $pendingShipments->groupBy('carrier_code');
                $successCount = 0;
                $failCount = 0;

                foreach ($byCarrier as $carrierCode => $shipments) {
                    // Use rate limiter per carrier
                    $results = BatchRateLimiter::forCarrier($carrierCode)
                        ->execute(
                            $shipments,
                            fn (Shipment $shipment) => $shipmentService->ship($shipment),
                            'ship'
                        );

                    foreach ($results as $result) {
                        if ($result['success']) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                    }
                }

                if ($successCount > 0) {
                    Notification::make()
                        ->title('Shipments Created')
                        ->body("{$successCount} shipment(s) created successfully.")
                        ->success()
                        ->send();
                }

                if ($failCount > 0) {
                    Notification::make()
                        ->title('Some Shipments Failed')
                        ->body("{$failCount} shipment(s) failed to create.")
                        ->warning()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'bulk_ship');
    }
}
