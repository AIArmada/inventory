<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeSetResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttributeSet extends EditRecord
{
    protected static string $resource = AttributeSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('setAttributes', $data)) {
            /** @var array<int, string>|null $attributes */
            $attributes = is_array($data['setAttributes'] ?? null) ? $data['setAttributes'] : null;
            $data['setAttributes'] = OwnerScope::ensureAllowed('setAttributes', Attribute::class, $attributes);
        }

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
