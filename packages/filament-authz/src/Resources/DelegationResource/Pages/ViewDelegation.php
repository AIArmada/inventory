<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\DelegationResource\Pages;

use AIArmada\FilamentAuthz\Resources\DelegationResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDelegation extends ViewRecord
{
    use EnsuresLivewireErrorBag;

    protected static string $resource = DelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
