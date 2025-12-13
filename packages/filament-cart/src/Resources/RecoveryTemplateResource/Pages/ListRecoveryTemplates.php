<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\RecoveryTemplateResource\Pages;

use AIArmada\FilamentCart\Resources\RecoveryTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecoveryTemplates extends ListRecords
{
    protected static string $resource = RecoveryTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
