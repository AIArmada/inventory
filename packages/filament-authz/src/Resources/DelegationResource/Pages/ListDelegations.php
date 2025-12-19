<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\DelegationResource\Pages;

use AIArmada\FilamentAuthz\Resources\DelegationResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDelegations extends ListRecords
{
    use EnsuresLivewireErrorBag;

    protected static string $resource = DelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
