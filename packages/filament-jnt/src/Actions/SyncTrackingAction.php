<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Actions;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntTrackingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class SyncTrackingAction
{
    public static function make(): Action
    {
        return Action::make('syncTracking')
            ->label('Sync Tracking')
            ->icon(Heroicon::ArrowPath)
            ->color('info')
            ->requiresConfirmation()
            ->authorize(fn (): bool => Auth::check())
            ->modalHeading('Sync Tracking Information')
            ->modalDescription('This will fetch the latest tracking information from J&T Express. Continue?')
            ->modalSubmitActionLabel('Sync Now')
            ->action(function (JntOrder $record): void {
                if (Auth::user() === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to sync tracking.')
                        ->danger()
                        ->send();

                    return;
                }

                if (! self::recordIsAccessible($record)) {
                    Notification::make()
                        ->title('Not Authorized')
                        ->body('You do not have access to this shipping order.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $trackingService = app(JntTrackingService::class);
                    $trackingService->syncOrderTracking($record);

                    $record->refresh();

                    Notification::make()
                        ->title('Tracking Synced')
                        ->body('Tracking information has been updated successfully.')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Sync Failed')
                        ->body('Unable to sync tracking. Please try again or check logs.')
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (JntOrder $record): bool => $record->tracking_number !== null);
    }

    private static function recordIsAccessible(JntOrder $record): bool
    {
        if (! config('jnt.owner.enabled', false)) {
            return true;
        }

        $owner = null;
        if (app()->bound(OwnerResolverInterface::class)) {
            $owner = app(OwnerResolverInterface::class)->resolve();
        }

        /** @var bool $includeGlobal */
        $includeGlobal = (bool) config('jnt.owner.include_global', true);

        return JntOrder::query()
            ->forOwner($owner, $includeGlobal)
            ->whereKey($record->getKey())
            ->exists();
    }
}
