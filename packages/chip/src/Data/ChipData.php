<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

abstract class ChipData extends Data
{
    /**
     * Resolve the incoming payload for a data object.
     *
     * @param  array<string, mixed>|self  ...$payloads
     * @return array<string, mixed>
     */
    protected static function resolvePayload(mixed ...$payloads): array
    {
        $data = $payloads[0] ?? [];

        if ($data instanceof self) {
            return $data->toArray();
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('Chip data payload must be an array.');
        }

        return $data;
    }
}
