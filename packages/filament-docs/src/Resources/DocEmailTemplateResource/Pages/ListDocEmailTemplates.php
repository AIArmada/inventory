<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocEmailTemplateResource\Pages;

use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use Filament\Resources\Pages\ListRecords;

final class ListDocEmailTemplates extends ListRecords
{
    protected static string $resource = DocEmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
