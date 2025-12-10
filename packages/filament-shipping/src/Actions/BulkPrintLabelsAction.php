<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\BulkAction;
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
            ->action(function (Collection $records): void {
                $shippingManager = app(ShippingManager::class);
                $labels = [];
                $errors = [];

                foreach ($records as $record) {
                    if (! $record instanceof Shipment || $record->tracking_number === null) {
                        continue;
                    }

                    try {
                        $driver = $shippingManager->driver($record->carrier_code);
                        $label = $driver->generateLabel($record->tracking_number);

                        if ($label->url !== null) {
                            $labels[] = [
                                'tracking' => $record->tracking_number,
                                'url' => $label->url,
                            ];
                        }
                    } catch (Throwable $e) {
                        $errors[] = "{$record->tracking_number}: {$e->getMessage()}";
                    }
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
