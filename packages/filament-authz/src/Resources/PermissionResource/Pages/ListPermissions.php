<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\PermissionResource\Pages;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    use EnsuresLivewireErrorBag;

    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
