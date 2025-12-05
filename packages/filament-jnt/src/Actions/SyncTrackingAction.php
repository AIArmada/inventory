<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Actions;

use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntTrackingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class SyncTrackingAction
{
    public static function make(): Action
    {
        return Action::make('syncTracking')
            ->label('Sync Tracking')
            ->icon(Heroicon::ArrowPath)
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Sync Tracking Information')
            ->modalDescription('This will fetch the latest tracking information from J&T Express. Continue?')
            ->modalSubmitActionLabel('Sync Now')
            ->action(function (JntOrder $record): void {
                try {
                    $trackingService = app(JntTrackingService::class);
                    $trackingService->syncOrderTracking($record);

                    Notification::make()
                        ->title('Tracking Synced')
                        ->body('Tracking information has been updated successfully.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Sync Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (JntOrder $record): bool => $record->tracking_number !== null);
    }
}
