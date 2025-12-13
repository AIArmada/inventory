<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocEmailTemplateResource\Pages;

use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDocEmailTemplate extends CreateRecord
{
    protected static string $resource = DocEmailTemplateResource::class;
}
