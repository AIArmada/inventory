<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Pages;

use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    use EnsuresLivewireErrorBag;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
