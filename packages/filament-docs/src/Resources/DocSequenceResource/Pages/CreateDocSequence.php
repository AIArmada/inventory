<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocSequenceResource\Pages;

use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDocSequence extends CreateRecord
{
    protected static string $resource = DocSequenceResource::class;
}
