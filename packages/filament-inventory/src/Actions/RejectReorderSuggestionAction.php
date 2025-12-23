<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

final class RejectReorderSuggestionAction
{
    public static function make(string $name = 'reject'): Action
    {
        return Action::make($name)
            ->label('Reject')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (InventoryReorderSuggestion $record): void {
                if (InventoryOwnerScope::isEnabled()) {
                    $allowNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::resolveOwner() === null;

                    if ($record->location_id === null && ! $allowNullLocation) {
                        Notification::make()
                            ->title('Not allowed')
                            ->body('This record is not available for the current owner context.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($record->location_id !== null) {
                        $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                            ->whereKey($record->location_id)
                            ->exists();

                        if (! $isAllowed) {
                            Notification::make()
                                ->title('Not allowed')
                                ->body('This record is not available for the current owner context.')
                                ->danger()
                                ->send();

                            return;
                        }
                    }
                }

                $rejected = $record->reject();

                if (! $rejected) {
                    Notification::make()
                        ->title('No changes')
                        ->body('This suggestion can no longer be rejected (it may already be processed).')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Rejected')
                    ->body('Reorder suggestion rejected.')
                    ->success()
                    ->send();
            });
    }
}
