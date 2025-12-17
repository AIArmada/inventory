<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
            ->authorize(fn (Shipment $record): bool => auth()->user()?->can('printLabel', $record) ?? false)
            ->action(function (Shipment $record): void {
                try {
                    $shippingManager = app(ShippingManager::class);
                    $driver = $shippingManager->driver($record->carrier_code);
                    $label = $driver->generateLabel($record->tracking_number);

                    if ($label->url !== null) {
                        $url = $label->url;
                        $scheme = parse_url($url, PHP_URL_SCHEME);

                        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                            Notification::make()
                                ->title('Invalid Label URL')
                                ->body('The carrier returned an invalid label URL.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $this->redirect($url, true);
                    } else {
                        Notification::make()
                            ->title('Label Not Available')
                            ->body('Label URL is not available for this shipment.')
                            ->warning()
                            ->send();
                    }
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Label Generation Failed')
                        ->body('Unable to generate label. Please try again or check logs.')
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
