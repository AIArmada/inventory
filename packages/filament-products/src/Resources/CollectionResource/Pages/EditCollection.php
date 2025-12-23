<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CollectionResource\Pages;

use AIArmada\FilamentProducts\Resources\CollectionResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('products', $data)) {
            /** @var array<int, string>|null $products */
            $products = is_array($data['products'] ?? null) ? $data['products'] : null;
            $data['products'] = OwnerScope::ensureAllowed('products', Product::class, $products);
        }

        if (isset($data['conditions']) && is_array($data['conditions'])) {
            foreach ($data['conditions'] as $index => $condition) {
                if (! is_array($condition)) {
                    continue;
                }

                if (($condition['field'] ?? null) !== 'category') {
                    continue;
                }

                $value = $condition['value'] ?? null;
                if (! is_string($value)) {
                    continue;
                }

                $allowed = OwnerScope::allowedIds(Category::class, [$value]);
                if ($allowed === []) {
                    unset($data['conditions'][$index]['value']);
                } else {
                    $data['conditions'][$index]['value'] = $allowed[0];
                }
            }
        }

        return $data;
    }
}
