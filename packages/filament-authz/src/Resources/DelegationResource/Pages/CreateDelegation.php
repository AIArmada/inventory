<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\DelegationResource\Pages;

use AIArmada\FilamentAuthz\Resources\DelegationResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Resources\Pages\CreateRecord;

class CreateDelegation extends CreateRecord
{
    use EnsuresLivewireErrorBag;

    protected static string $resource = DelegationResource::class;
}
