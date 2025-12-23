<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Pages;

use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\Category;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('categories', $data)) {
            /** @var array<int, string>|null $categories */
            $categories = is_array($data['categories'] ?? null) ? $data['categories'] : null;
            $data['categories'] = OwnerScope::ensureAllowed('categories', Category::class, $categories);
        }

        // Ensure prices are stored in cents
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
