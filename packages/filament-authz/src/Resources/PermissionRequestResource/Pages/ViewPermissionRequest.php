<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages;

use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPermissionRequest extends ViewRecord
{
    protected static string $resource = PermissionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
