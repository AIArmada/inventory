<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages;

use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermissionRequests extends ListRecords
{
    use EnsuresLivewireErrorBag;

    protected static string $resource = PermissionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
