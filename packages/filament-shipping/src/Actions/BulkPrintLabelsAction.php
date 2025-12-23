<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
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
            ->action(function (Collection $records): void {
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
                        $label = $driver->generateLabel($record->tracking_number);

                        if ($label->url !== null) {
                            $url = $label->url;
                            $scheme = parse_url($url, PHP_URL_SCHEME);

                            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                                $errors[] = "{$record->tracking_number}: invalid label URL";

                                continue;
                            }

                            $labels[] = [
                                'tracking' => $record->tracking_number,
                                'url' => $url,
                            ];
                        }
                    } catch (Throwable $e) {
                        report($e);
                        $errors[] = "{$record->tracking_number}: error generating label";
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
                    $this->redirect($labels[0]['url'], true);

                    return;
                }

                if (count($labels) > 0) {
                    Notification::make()
                        ->title('Labels Generated')
                        ->body(count($labels) . ' label(s) ready. Opening in new tabs...')
                        ->success()
                        ->send();

                    // For multiple labels, we'll show them as a list
                    foreach ($labels as $label) {
                        Notification::make()
                            ->title($label['tracking'])
                            ->body('Click to open label')
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('open')
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
                        ->body(implode("\n", array_slice($errors, 0, 3)))
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
