<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

final class PromotionsOwnerScope
{
    public static function isEnabled(): bool
    {
        return (bool) config('promotions.features.owner.enabled', false);
    }

    public static function includeGlobal(): bool
    {
        return (bool) config('promotions.features.owner.include_global', true);
    }

    public static function resolveOwner(): ?Model
    {
        if (! self::isEnabled()) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
