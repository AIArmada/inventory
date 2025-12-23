<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\Inventory\Facades\InventoryAllocation as InventoryAllocationFacade;
use AIArmada\Inventory\Models\InventoryAllocation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class ReleaseAllocationAction
{
    public static function make(string $name = 'release'): Action
    {
        return Action::make($name)
            ->label('Release')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Release Allocation')
            ->modalDescription('This will release the allocated inventory back to available stock.')
            ->action(function (InventoryAllocation $record): void {
                $released = InventoryAllocationFacade::releaseAllocation($record);

                if ($released <= 0) {
                    Notification::make()
                        ->title('Release Failed')
                        ->body('This allocation is not available for the current owner context (or it has already been released).')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Allocation Released')
                    ->body("Released {$released} units back to inventory.")
                    ->success()
                    ->send();
            });
    }
}
