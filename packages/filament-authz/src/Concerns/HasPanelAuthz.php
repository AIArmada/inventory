<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Trait for automatic panel access control on User models.
 *
 * Usage:
 *   class User extends Authenticatable
 *   {
 *       use HasRoles, HasPanelAuthz;
 *   }
 */
trait HasPanelAuthz
{
    /**
     * Boot the trait.
     */
    public static function bootHasPanelAuthz(): void
    {
        $panelUserRole = config('filament-authz.panel_user_role');

        if ($panelUserRole !== null) {
            // Auto-assign panel user role on creation
            static::created(function (self $user): void {
                $panelUserRole = config('filament-authz.panel_user_role');
                if ($panelUserRole !== null && method_exists($user, 'assignRole')) {
                    try {
                        $user->assignRole($panelUserRole);
                    } catch (Throwable) {
                        // Role might not exist yet
                    }
                }
            });
        }
    }

    /**
     * Check if user can access a specific panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Super admin has access to all panels
        $superAdminRole = config('filament-authz.super_admin_role', 'super_admin');
        if (method_exists($this, 'hasRole') && $this->hasRole($superAdminRole)) {
            return true;
        }

        // Check panel-specific roles
        $panelRoles = config("filament-authz.panel_roles.{$panel->getId()}", []);

        if (! empty($panelRoles)) {
            if (! method_exists($this, 'hasAnyRole')) {
                return false;
            }

            return $this->hasAnyRole($panelRoles);
        }

        // Fallback to panel user role
        $panelUserRole = config('filament-authz.panel_user_role');
        if ($panelUserRole !== null && method_exists($this, 'hasRole')) {
            return $this->hasRole($panelUserRole);
        }

        return false;
    }

    /**
     * Get panels this user can access.
     *
     * @return Collection<string, Panel>
     */
    public function getAccessiblePanels(): Collection
    {
        return collect(Filament::getPanels())
            ->filter(fn (Panel $panel): bool => $this->canAccessPanel($panel));
    }

    /**
     * Check if user has access to any panel.
     */
    public function hasAnyPanelAccess(): bool
    {
        return $this->getAccessiblePanels()->isNotEmpty();
    }

    /**
     * Get the default panel for this user.
     */
    public function getDefaultPanel(): ?Panel
    {
        $accessiblePanels = $this->getAccessiblePanels();

        if ($accessiblePanels->isEmpty()) {
            return null;
        }

        // Return the first accessible panel
        return $accessiblePanels->first();
    }
}
