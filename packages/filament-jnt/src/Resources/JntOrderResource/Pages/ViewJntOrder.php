<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntOrderResource\Pages;

use AIArmada\FilamentJnt\Actions\CancelOrderAction;
use AIArmada\FilamentJnt\Actions\PrintAwbAction;
use AIArmada\FilamentJnt\Actions\SyncTrackingAction;
use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\FilamentJnt\Resources\Pages\ReadOnlyViewRecord;
use Filament\Support\Icons\Heroicon;
use Override;

final class ViewJntOrder extends ReadOnlyViewRecord
{
    protected static string $resource = JntOrderResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Order %s', $record->order_id ?? $record->getKey());
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedTruck;
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            PrintAwbAction::make(),
            SyncTrackingAction::make(),
            CancelOrderAction::make(),
        ];
    }
}
