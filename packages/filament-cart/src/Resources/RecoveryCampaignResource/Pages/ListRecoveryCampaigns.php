<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\RecoveryCampaignResource\Pages;

use AIArmada\FilamentCart\Resources\RecoveryCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecoveryCampaigns extends ListRecords
{
    protected static string $resource = RecoveryCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
