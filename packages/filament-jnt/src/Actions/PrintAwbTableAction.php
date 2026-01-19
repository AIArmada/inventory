<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Data\PrintWaybillData;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Throwable;

final class PrintAwbTableAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('printAwb')
            ->label('Print AWB')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->requiresConfirmation()
            ->authorize(fn (): bool => Filament::auth()?->check() ?? false)
            ->modalHeading('Print Air Waybill')
            ->modalDescription('Generate and print the shipping label for this order.')
            ->modalSubmitActionLabel('Print')
            ->action(function (JntOrder $record, Component $livewire): void {
                if (Filament::auth()?->user() === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to print AWB.')
                        ->danger()
                        ->send();

                    return;
                }

                if (! self::recordIsAccessible($record)) {
                    Notification::make()
                        ->title('Not Authorized')
                        ->body('You do not have access to this shipping order.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $jntService = app(JntExpressService::class);

                    $result = $record->tracking_number !== null
                        ? $jntService->printOrder($record->order_id, $record->tracking_number)
                        : $jntService->printOrder($record->order_id);

                    $waybill = PrintWaybillData::fromApiArray($result);

                    if ($waybill->hasUrlContent()) {
                        $url = $waybill->urlContent;

                        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                            $livewire->js("window.open('{$url}', '_blank')");

                            Notification::make()
                                ->title('AWB Ready')
                                ->body('Opening waybill in new tab.')
                                ->success()
                                ->send();

                            return;
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

                        $livewire->js("window.open('{$url}', '_blank')");

                        Notification::make()
                            ->title('AWB Generated')
                            ->body('Opening waybill in new tab.')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('AWB Not Available')
                        ->body('Unable to retrieve waybill content from J&T Express.')
                        ->warning()
                        ->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Print Failed')
                        ->body('Unable to generate AWB. Please try again or check logs.')
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (JntOrder $record): bool => $record->order_id !== '' && $record->order_id !== null);
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'printAwb');
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
