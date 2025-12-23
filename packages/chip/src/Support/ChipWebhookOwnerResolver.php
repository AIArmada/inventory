<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

final class ChipWebhookOwnerResolver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function resolveFromPayload(array $payload): ?Model
    {
        $brandId = self::extractBrandId($payload);

        if ($brandId === null || $brandId === '') {
            return null;
        }

        return self::resolveFromBrandId($brandId);
    }

    public static function resolveFromBrandId(string $brandId): ?Model
    {
        $map = config('chip.owner.webhook_brand_id_map', []);

        if (! is_array($map)) {
            return null;
        }

        $entry = $map[$brandId] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        $ownerType = $entry['owner_type'] ?? $entry['type'] ?? null;
        $ownerId = $entry['owner_id'] ?? $entry['id'] ?? null;

        if (! is_string($ownerType) || $ownerType === '') {
            return null;
        }

        if (! is_string($ownerId) && ! is_int($ownerId)) {
            return null;
        }

        return OwnerContext::fromTypeAndId($ownerType, $ownerId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function extractBrandId(array $payload): ?string
    {
        $brandId = $payload['brand_id'] ?? null;

        if (is_string($brandId) && $brandId !== '') {
            return $brandId;
        }

        $purchase = $payload['purchase'] ?? null;

        if (is_array($purchase)) {
            $nestedBrandId = $purchase['brand_id'] ?? null;

            if (is_string($nestedBrandId) && $nestedBrandId !== '') {
                return $nestedBrandId;
            }
        }

        return null;
    }
}
