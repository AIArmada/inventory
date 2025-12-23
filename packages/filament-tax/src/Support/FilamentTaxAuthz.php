<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Support;

use Filament\Actions\Action;

final class FilamentTaxAuthz
{
    /**
     * Apply authorization to a Filament action.
     *
     * - If filament-authz is installed, use its `requiresPermission()` macro.
     * - Otherwise, fail closed for unauthenticated users.
     */
    public static function requirePermission(Action $action, string $permission): Action
    {
        $action->authorize(fn (): bool => auth()->check());

        if (! Action::hasMacro('requiresPermission')) {
            return $action;
        }

        /** @phpstan-ignore-next-line method.notFound */
        return $action->requiresPermission($permission);
    }
}
