<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Pages;

use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\Category;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle parent from URL query parameter (validated + owner-scoped)
        $parentId = request()->query('parent');
        if (is_string($parentId) && Str::isUuid($parentId)) {
            $allowed = OwnerScope::allowedIds(Category::class, [$parentId]);

            if ($allowed !== []) {
                $data['parent_id'] = $allowed[0];
            }
        }

        if (isset($data['parent_id']) && is_string($data['parent_id'])) {
            $allowed = OwnerScope::allowedIds(Category::class, [$data['parent_id']]);

            if ($allowed === []) {
                unset($data['parent_id']);
            }
        }

        return $data;
    }
}
