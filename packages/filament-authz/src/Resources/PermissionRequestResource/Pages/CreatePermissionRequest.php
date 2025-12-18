<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages;

use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Resources\Pages\CreateRecord;

class CreatePermissionRequest extends CreateRecord
{
    use EnsuresLivewireErrorBag;

    protected static string $resource = PermissionRequestResource::class;
}
