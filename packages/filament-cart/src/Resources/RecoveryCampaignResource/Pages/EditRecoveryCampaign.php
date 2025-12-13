<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\RecoveryCampaignResource\Pages;

use AIArmada\FilamentCart\Resources\RecoveryCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecoveryCampaign extends EditRecord
{
    protected static string $resource = RecoveryCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
