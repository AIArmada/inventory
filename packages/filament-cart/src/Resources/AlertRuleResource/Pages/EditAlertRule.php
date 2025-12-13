<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\AlertRuleResource\Pages;

use AIArmada\FilamentCart\Resources\AlertRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAlertRule extends EditRecord
{
    protected static string $resource = AlertRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
