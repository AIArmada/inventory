<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Pages;

use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\Category;
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
        if (array_key_exists('categories', $data)) {
            /** @var array<int, string>|null $categories */
            $categories = is_array($data['categories'] ?? null) ? $data['categories'] : null;
            $data['categories'] = OwnerScope::ensureAllowed('categories', Category::class, $categories);
        }

        // Convert prices to cents for storage
        if (isset($data['price']) && is_numeric($data['price'])) {
            $data['price'] = (int) round(((float) $data['price']) * 100);
        }
        if (isset($data['compare_price']) && is_numeric($data['compare_price'])) {
            $data['compare_price'] = (int) round(((float) $data['compare_price']) * 100);
        }
        if (isset($data['cost']) && is_numeric($data['cost'])) {
            $data['cost'] = (int) round(((float) $data['cost']) * 100);
        }

        return $data;
    }
}
