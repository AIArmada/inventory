<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\RecoveryTemplateResource\Pages;

use AIArmada\FilamentCart\Resources\RecoveryTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecoveryTemplate extends ViewRecord
{
    protected static string $resource = RecoveryTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
