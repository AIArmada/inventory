<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\RecoveryCampaignResource\Pages;

use AIArmada\FilamentCart\Resources\RecoveryCampaignResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecoveryCampaign extends ViewRecord
{
    protected static string $resource = RecoveryCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
