<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\ConditionResource\Pages;

use AIArmada\Cart\Models\Condition;
use AIArmada\FilamentCart\Resources\ConditionResource;
use AIArmada\FilamentCart\Services\CartConditionBatchRemoval;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditCondition extends EditRecord
{
    protected static string $resource = ConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('removeFromAllCarts')
                ->label('Remove from All Carts')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Remove Condition from All Carts')
                ->modalDescription('This will immediately remove this condition from all active carts. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Remove from All Carts')
                ->visible(fn (Condition $record) => $record->is_global)
                ->action(function (Condition $record): void {
                    $batchRemoval = app(CartConditionBatchRemoval::class);
                    $result = $batchRemoval->removeConditionFromAllCarts($record->name);

                    if ($result['success']) {
                        Notification::make()
                            ->title('Condition Removed from All Carts')
                            ->body("Processed {$result['carts_processed']} carts, updated {$result['carts_updated']} carts.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Error Removing Condition')
                            ->body('Failed to remove condition from all carts. Check logs for details.')
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['rules'] = Condition::normalizeRulesDefinition(
            $data['rules'] ?? null,
            ! empty($data['rules']['factory_keys'] ?? [])
        );

        return $data;
    }
}
