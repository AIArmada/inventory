<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\DelegationResource\Pages;

use AIArmada\FilamentAuthz\Resources\DelegationResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDelegation extends EditRecord
{
    use EnsuresLivewireErrorBag;

    protected static string $resource = DelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
