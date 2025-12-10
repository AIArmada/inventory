<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\TrackingAggregator;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Throwable;

class SyncTrackingAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('sync_tracking')
            ->label('Sync Tracking')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('info')
            ->visible(fn (Shipment $record): bool => $record->tracking_number !== null)
            ->action(function (Shipment $record): void {
                try {
                    $aggregator = app(TrackingAggregator::class);
                    $tracking = $aggregator->track($record->carrier_code, $record->tracking_number);

                    if ($tracking !== null) {
                        $record->update([
                            'tracking_status' => $tracking->status->value,
                            'last_tracking_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Tracking Updated')
                            ->body("Status: {$tracking->status->getLabel()}")
                            ->success()
                            ->send();
                    }
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Tracking Sync Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'sync_tracking');
    }
}
