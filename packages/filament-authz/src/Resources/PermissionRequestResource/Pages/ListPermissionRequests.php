<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages;

use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListPermissionRequests extends ListRecords
{
    protected static string $resource = PermissionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
