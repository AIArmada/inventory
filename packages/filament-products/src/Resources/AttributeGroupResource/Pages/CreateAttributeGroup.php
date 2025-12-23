<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeGroupResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\Attribute;
use Filament\Resources\Pages\CreateRecord;

class CreateAttributeGroup extends CreateRecord
{
    protected static string $resource = AttributeGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('attributes', $data)) {
            /** @var array<int, string>|null $attributes */
            $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : null;
            $data['attributes'] = OwnerScope::ensureAllowed('attributes', Attribute::class, $attributes);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
