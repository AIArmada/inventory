<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\BatchRateLimiter;
use AIArmada\Shipping\Services\TrackingAggregator;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
            ->authorize(fn (): bool => auth()->user()?->can('shipping.shipments.sync-tracking') ?? false)
            ->action(function (Collection $records): void {
                $user = auth()->user();

                if ($user === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to sync tracking.')
                        ->danger()
                        ->send();

                    return;
                }

                $aggregator = app(TrackingAggregator::class);

                // Filter to only trackable shipments
                $trackableShipments = $records->filter(
                    fn ($record) => $record instanceof Shipment
                        && $record->tracking_number !== null
                        && $user->can('syncTracking', $record)
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
                            fn (Shipment $shipment) => $aggregator->syncTracking($shipment),
                            'sync_tracking'
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
