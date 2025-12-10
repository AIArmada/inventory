<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\BatchRateLimiter;
use AIArmada\Shipping\Services\ShipmentService;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class BulkCancelAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('bulk_cancel')
            ->label('Cancel Selected')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel Selected Shipments')
            ->modalDescription('Are you sure you want to cancel all selected shipments? This action cannot be undone.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $shipmentService = app(ShipmentService::class);

                $cancellableStatuses = [
                    ShipmentStatus::Draft,
                    ShipmentStatus::Pending,
                    ShipmentStatus::Shipped,
                ];

                // Filter to only cancellable shipments
                $cancellableShipments = $records->filter(
                    fn ($record) => $record instanceof Shipment
                    && in_array($record->status, $cancellableStatuses, true)
                );

                if ($cancellableShipments->isEmpty()) {
                    Notification::make()
                        ->title('No Cancellable Shipments')
                        ->body('None of the selected records can be cancelled.')
                        ->warning()
                        ->send();

                    return;
                }

                // Group by carrier for rate limiting
                $byCarrier = $cancellableShipments->groupBy('carrier_code');
                $successCount = 0;
                $failCount = 0;

                foreach ($byCarrier as $carrierCode => $shipments) {
                    // Use rate limiter per carrier
                    $results = BatchRateLimiter::forCarrier($carrierCode)
                        ->execute(
                            $shipments,
                            fn (Shipment $shipment) => $shipmentService->cancel($shipment),
                            'cancel'
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
                        ->title('Shipments Cancelled')
                        ->body("{$successCount} shipment(s) cancelled successfully.")
                        ->success()
                        ->send();
                }

                if ($failCount > 0) {
                    Notification::make()
                        ->title('Some Cancellations Failed')
                        ->body("{$failCount} shipment(s) could not be cancelled.")
                        ->warning()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'bulk_cancel');
    }
}
