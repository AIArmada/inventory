<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;

/**
 * Auto-assign the current owner on model creation when enabled.
 */
trait AutoAssignOwnerOnCreate
{
    protected static function bootAutoAssignOwnerOnCreate(): void
    {
        static::creating(function (self $model): void {
            if (! (bool) config('chip.owner.enabled', true)) {
                return;
            }

            if (! (bool) config('chip.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($model->hasOwner()) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner === null) {
                return;
            }

            $model->assignOwner($owner);
        });
    }
}
