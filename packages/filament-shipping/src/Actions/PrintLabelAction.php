<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Throwable;

class PrintLabelAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('print_label')
            ->label('Print Label')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (Shipment $record): bool => $record->tracking_number !== null)
            ->action(function (Shipment $record): void {
                try {
                    $shippingManager = app(ShippingManager::class);
                    $driver = $shippingManager->driver($record->carrier_code);
                    $label = $driver->generateLabel($record->tracking_number);

                    if ($label->url !== null) {
                        $this->redirect($label->url, true);
                    } else {
                        Notification::make()
                            ->title('Label Not Available')
                            ->body('Label URL is not available for this shipment.')
                            ->warning()
                            ->send();
                    }
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Label Generation Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'print_label');
    }
}
