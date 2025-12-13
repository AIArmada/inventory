<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\RecoveryTemplateResource\Pages;

use AIArmada\FilamentCart\Resources\RecoveryTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecoveryTemplate extends EditRecord
{
    protected static string $resource = RecoveryTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
