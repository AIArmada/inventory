<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocEmailTemplateResource\Pages;

use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditDocEmailTemplate extends EditRecord
{
    protected static string $resource = DocEmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
