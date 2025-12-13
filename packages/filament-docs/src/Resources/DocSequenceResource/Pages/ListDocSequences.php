<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocSequenceResource\Pages;

use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use Filament\Resources\Pages\ListRecords;

final class ListDocSequences extends ListRecords
{
    protected static string $resource = DocSequenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
