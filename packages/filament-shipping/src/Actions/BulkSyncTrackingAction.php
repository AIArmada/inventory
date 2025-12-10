<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\BatchRateLimiter;
use AIArmada\Shipping\Services\TrackingAggregator;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class BulkSyncTrackingAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('bulk_sync_tracking')
            ->label('Sync Tracking')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('info')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $aggregator = app(TrackingAggregator::class);

                // Filter to only trackable shipments
                $trackableShipments = $records->filter(
                    fn ($record) => $record instanceof Shipment && $record->tracking_number !== null
                );

                if ($trackableShipments->isEmpty()) {
                    Notification::make()
                        ->title('No Trackable Shipments')
                        ->body('None of the selected records have tracking numbers.')
                        ->warning()
                        ->send();

                    return;
                }

                // Group by carrier for rate limiting
                $byCarrier = $trackableShipments->groupBy('carrier_code');
                $successCount = 0;
                $failCount = 0;

                foreach ($byCarrier as $carrierCode => $shipments) {
                    // Use rate limiter per carrier
                    $results = BatchRateLimiter::forCarrier($carrierCode)
                        ->execute(
                            $shipments,
                            function (Shipment $shipment) use ($aggregator) {
                                $tracking = $aggregator->track($shipment->carrier_code, $shipment->tracking_number);

                                if ($tracking !== null) {
                                    $shipment->update([
                                        'last_tracking_sync' => now(),
                                    ]);

                                    return $tracking;
                                }

                                return null;
                            },
                            'sync_tracking'
                        );

                    foreach ($results as $result) {
                        if ($result['success'] && $result['result'] !== null) {
                            $successCount++;
                        } elseif (! $result['success']) {
                            $failCount++;
                        }
                    }
                }

                if ($successCount > 0) {
                    Notification::make()
                        ->title('Tracking Updated')
                        ->body("{$successCount} shipment(s) updated successfully.")
                        ->success()
                        ->send();
                }

                if ($failCount > 0) {
                    Notification::make()
                        ->title('Some Updates Failed')
                        ->body("{$failCount} shipment(s) could not be updated.")
                        ->warning()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'bulk_sync_tracking');
    }
}
