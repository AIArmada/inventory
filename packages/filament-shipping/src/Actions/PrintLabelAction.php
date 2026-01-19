<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
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
            ->action(function (Shipment $record, Component $livewire): mixed {
                try {
                    $shippingManager = app(ShippingManager::class);
                    $driver = $shippingManager->driver($record->carrier_code);
                    $label = $driver->generateLabel($record->tracking_number, [
                        'order_id' => $record->reference,
                    ]);

                    if ($label->hasUrl()) {
                        $url = $label->url;
                        $scheme = parse_url((string) $url, PHP_URL_SCHEME);

                        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                            Notification::make()
                                ->title('Invalid Label URL')
                                ->body('The carrier returned an invalid label URL.')
                                ->warning()
                                ->send();

                            return null;
                        }

                        $livewire->js("window.open('{$url}', '_blank')");

                        return null;
                    }

                    if ($label->hasContent()) {
                        $cacheKey = "shipping_label:{$record->tracking_number}";
                        Cache::put($cacheKey, [
                            'content' => $label->getDecodedContent(),
                            'format' => $label->format,
                        ], now()->addMinutes(30));

                        $url = URL::signedRoute('shipping.labels.show', [
                            'trackingNumber' => $record->tracking_number,
                        ], now()->addMinutes(30));

                        $livewire->js("window.open('{$url}', '_blank')");

                        return null;
                    }

                    Notification::make()
                        ->title('Label Not Available')
                        ->body('Label URL is not available for this shipment.')
                        ->warning()
                        ->send();

                    return null;
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Label Generation Failed')
                        ->body('Unable to generate label. Please try again or check logs.')
                        ->danger()
                        ->send();

                    return null;
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'print_label');
    }
}
