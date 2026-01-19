<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Filament\Actions\BulkAction;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Throwable;

class BulkPrintLabelsAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('bulk_print_labels')
            ->label('Print Labels')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => auth()->user() !== null)
            ->requiresConfirmation()
            ->modalHeading('Print Shipping Labels')
            ->modalDescription('Generate and print labels for selected shipments.')
            ->modalSubmitActionLabel('Print All')
            ->action(function (Collection $records, Component $livewire): void {
                $user = auth()->user();

                if ($user === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to print labels.')
                        ->danger()
                        ->send();

                    return;
                }

                $shippingManager = app(ShippingManager::class);
                $labels = [];
                $errors = [];

                foreach ($records as $record) {
                    if (! $record instanceof Shipment || $record->tracking_number === null) {
                        continue;
                    }

                    if (! $user->can('printLabel', $record)) {
                        continue;
                    }

                    try {
                        $driver = $shippingManager->driver($record->carrier_code);
                        $label = $driver->generateLabel($record->tracking_number, [
                            'order_id' => $record->reference,
                        ]);

                        if ($label->hasUrl()) {
                            $url = $label->url;
                            $scheme = parse_url((string) $url, PHP_URL_SCHEME);

                            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                                $errors[] = "{$record->tracking_number}: invalid label URL";

                                continue;
                            }

                            $labels[] = [
                                'tracking' => $record->tracking_number,
                                'carrier' => $record->carrier_code,
                                'url' => $url,
                                'type' => 'url',
                            ];

                            continue;
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

                            $labels[] = [
                                'tracking' => $record->tracking_number,
                                'carrier' => $record->carrier_code,
                                'url' => $url,
                                'type' => 'cached',
                            ];

                            continue;
                        }

                        $errors[] = "{$record->tracking_number}: no label content";
                    } catch (Throwable $e) {
                        report($e);
                        $errors[] = "{$record->tracking_number}: " . $e->getMessage();
                    }
                }

                if (count($labels) === 0 && count($errors) === 0) {
                    Notification::make()
                        ->title('No Printable Labels')
                        ->body('None of the selected shipments have printable labels.')
                        ->warning()
                        ->send();

                    return;
                }

                if (count($labels) === 1) {
                    $livewire->js("window.open('{$labels[0]['url']}', '_blank')");

                    Notification::make()
                        ->title('Label Ready')
                        ->body("Opening label for {$labels[0]['tracking']} in new tab.")
                        ->success()
                        ->send();

                    return;
                }

                if (count($labels) > 0) {
                    Notification::make()
                        ->title('Labels Generated')
                        ->body(count($labels) . ' label(s) ready. Click each to open.')
                        ->success()
                        ->send();

                    foreach ($labels as $label) {
                        $carrierName = ucfirst($label['carrier']);

                        Notification::make()
                            ->title("{$carrierName}: {$label['tracking']}")
                            ->body('Click to open shipping label')
                            ->actions([
                                NotificationAction::make('open')
                                    ->label('Open Label')
                                    ->url($label['url'], true),
                            ])
                            ->persistent()
                            ->send();
                    }
                }

                if (count($errors) > 0) {
                    Notification::make()
                        ->title('Some Labels Failed')
                        ->body(implode("\n", array_slice($errors, 0, 5)))
                        ->warning()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'bulk_print_labels');
    }
}
