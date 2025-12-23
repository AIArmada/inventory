<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeResource\Pages;

use AIArmada\FilamentProducts\Resources\AttributeResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\AttributeGroup;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
