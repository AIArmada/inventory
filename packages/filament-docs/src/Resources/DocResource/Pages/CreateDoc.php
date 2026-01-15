<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\FilamentDocs\Resources\DocResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDoc extends CreateRecord
{
    protected static string $resource = DocResource::class;

    protected function handleRecordCreation(array $data): Doc
    {
        $docService = app(DocService::class);

        $data['generate_pdf'] = $data['generate_pdf'] ?? (bool) config('filament-docs.features.auto_generate_pdf', true);

        return $docService->create(DocData::from($data));
    }
}
