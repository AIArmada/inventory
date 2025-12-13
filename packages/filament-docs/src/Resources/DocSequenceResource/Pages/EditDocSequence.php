<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocSequenceResource\Pages;

use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditDocSequence extends EditRecord
{
    protected static string $resource = DocSequenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
