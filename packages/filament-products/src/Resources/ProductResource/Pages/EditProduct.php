<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Pages;

use AIArmada\FilamentProducts\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert prices from cents to display values
        if (isset($data['price'])) {
            $data['price'] /= 100;
        }
        if (isset($data['compare_price'])) {
            $data['compare_price'] /= 100;
        }
        if (isset($data['cost'])) {
            $data['cost'] /= 100;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert prices to cents for storage
        if (isset($data['price']) && is_numeric($data['price'])) {
            $data['price'] = (int) ($data['price'] * 100);
        }
        if (isset($data['compare_price']) && is_numeric($data['compare_price'])) {
            $data['compare_price'] = (int) ($data['compare_price'] * 100);
        }
        if (isset($data['cost']) && is_numeric($data['cost'])) {
            $data['cost'] = (int) ($data['cost'] * 100);
        }

        return $data;
    }
}
