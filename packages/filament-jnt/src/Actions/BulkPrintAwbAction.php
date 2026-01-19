<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Data\PrintWaybillData;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Throwable;

final class BulkPrintAwbAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('bulk_print_awb')
            ->label('Print AWBs')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => Filament::auth()?->check() ?? false)
            ->requiresConfirmation()
            ->modalHeading('Print Air Waybills')
            ->modalDescription('Generate and print shipping labels for selected orders.')
            ->modalSubmitActionLabel('Print All')
            ->action(function (Collection $records, Component $livewire): void {
                if (Filament::auth()?->user() === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to print AWBs.')
                        ->danger()
                        ->send();

                    return;
                }

                $jntService = app(JntExpressService::class);
                $labels = [];
                $errors = [];

                foreach ($records as $record) {
                    if (! $record instanceof JntOrder) {
                        continue;
                    }

                    if (! self::recordIsAccessible($record)) {
                        $errors[] = "{$record->order_id}: access denied";

                        continue;
                    }

                    if ($record->order_id === '' || $record->order_id === null) {
                        continue;
                    }

                    try {
                        $result = $record->tracking_number !== null
                            ? $jntService->printOrder($record->order_id, $record->tracking_number)
                            : $jntService->printOrder($record->order_id);

                        $waybill = PrintWaybillData::fromApiArray($result);

                        if ($waybill->hasUrlContent()) {
                            $url = $waybill->urlContent;

                            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                                $labels[] = [
                                    'order_id' => $record->order_id,
                                    'tracking' => $record->tracking_number ?? $record->order_id,
                                    'url' => $url,
                                    'type' => 'url',
                                ];

                                continue;
                            }
                        }

                        if ($waybill->hasBase64Content()) {
                            $cacheKey = "jnt_awb:{$record->order_id}";
                            Cache::put($cacheKey, [
                                'content' => base64_decode((string) $waybill->base64Content, true),
                                'format' => 'pdf',
                            ], now()->addMinutes(30));

                            $url = URL::signedRoute('jnt.awb.show', [
                                'orderId' => $record->order_id,
                            ], now()->addMinutes(30));

                            $labels[] = [
                                'order_id' => $record->order_id,
                                'tracking' => $record->tracking_number ?? $record->order_id,
                                'url' => $url,
                                'type' => 'cached',
                            ];

                            continue;
                        }

                        $errors[] = "{$record->order_id}: no label content";
                    } catch (Throwable $e) {
                        report($e);
                        $errors[] = "{$record->order_id}: " . $e->getMessage();
                    }
                }

                if (count($labels) === 0 && count($errors) === 0) {
                    Notification::make()
                        ->title('No Printable AWBs')
                        ->body('None of the selected orders have printable waybills.')
                        ->warning()
                        ->send();

                    return;
                }

                if (count($labels) === 1) {
                    $livewire->js("window.open('{$labels[0]['url']}', '_blank')");

                    Notification::make()
                        ->title('AWB Ready')
                        ->body("Opening waybill for {$labels[0]['tracking']} in new tab.")
                        ->success()
                        ->send();

                    return;
                }

                if (count($labels) > 0) {
                    Notification::make()
                        ->title('AWBs Generated')
                        ->body(count($labels) . ' waybill(s) ready. Click each to open.')
                        ->success()
                        ->send();

                    foreach ($labels as $label) {
                        Notification::make()
                            ->title("AWB: {$label['tracking']}")
                            ->body("Order: {$label['order_id']}")
                            ->actions([
                                NotificationAction::make('open')
                                    ->label('Open AWB')
                                    ->url($label['url'], true),
                            ])
                            ->persistent()
                            ->send();
                    }
                }

                if (count($errors) > 0) {
                    Notification::make()
                        ->title('Some AWBs Failed')
                        ->body(implode("\n", array_slice($errors, 0, 5)))
                        ->warning()
                        ->send();
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'bulk_print_awb');
    }

    private static function recordIsAccessible(JntOrder $record): bool
    {
        if (! config('jnt.owner.enabled', false)) {
            return true;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        return JntOrder::query()
            ->forOwner($owner, $includeGlobal)
            ->whereKey($record->getKey())
            ->exists();
    }
}
