<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\DelegationResource\Pages;

use AIArmada\FilamentAuthz\Resources\DelegationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDelegation extends CreateRecord
{
    protected static string $resource = DelegationResource::class;
}
