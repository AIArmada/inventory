<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\AttributeGroup;
use Filament\Resources\Pages\CreateRecord;

class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('groups', $data)) {
            /** @var array<int, string>|null $groups */
            $groups = is_array($data['groups'] ?? null) ? $data['groups'] : null;
            $data['groups'] = OwnerScope::ensureAllowed('groups', AttributeGroup::class, $groups);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
