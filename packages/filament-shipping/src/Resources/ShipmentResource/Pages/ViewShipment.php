<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShipmentResource\Pages;

use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\ShipmentService;
use Exception;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewShipment extends ViewRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('ship')
                ->label('Ship')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Shipment $record) => $record->isPending())
                ->authorize(fn (Shipment $record): bool => auth()->user()?->can('ship', $record) ?? false)
                ->action(function (Shipment $record): void {
                    try {
                        app(ShipmentService::class)->ship($record);

                        Notification::make()
                            ->title('Shipment Shipped')
                            ->body("Tracking number: {$record->tracking_number}")
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Ship Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Shipment $record) => $record->isCancellable())
                ->authorize(fn (Shipment $record): bool => auth()->user()?->can('cancel', $record) ?? false)
                ->action(function (Shipment $record): void {
                    try {
                        app(ShipmentService::class)->cancel($record);

                        Notification::make()
                            ->title('Shipment Cancelled')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Cancel Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('print_label')
                ->label('Print Label')
                ->icon('heroicon-o-printer')
                ->visible(fn (Shipment $record) => $record->label_url !== null)
                ->authorize(fn (Shipment $record): bool => auth()->user()?->can('printLabel', $record) ?? false)
                ->url(fn (Shipment $record) => $record->label_url)
                ->openUrlInNewTab(),
        ];
    }
}
