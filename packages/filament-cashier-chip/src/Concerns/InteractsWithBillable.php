<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared functionality for billing portal pages.
 *
 * Provides common methods for resolving the billable model
 * and accessing billing-related data.
 */
trait InteractsWithBillable
{
    /**
     * Get the billable model for the current user.
     *
     * Resolves the billable in the following order:
     * 1. If user matches configured billable_model, return user
     * 2. If user has currentTeam method, return the team
     * 3. Fall back to the user
     */
    protected function getBillable(): ?Model
    {
        $user = filament()->auth()->user();

        if (! $user instanceof Model) {
            return null;
        }

        $billableModel = config('filament-cashier-chip.billing.billable_model');

        if ($billableModel && $user instanceof $billableModel) {
            return $user;
        }

        if (method_exists($user, 'currentTeam')) {
            $team = $user->currentTeam;

            return $team instanceof Model ? $team : $user;
        }

        return $user;
    }

    /**
     * Get payment methods for the billable.
     *
     * @return Collection<int, mixed>
     */
    protected function getPaymentMethods(): Collection
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'paymentMethods')) {
            return collect();
        }

        return $billable->paymentMethods();
    }

    /**
     * Get the default payment method for the billable.
     */
    protected function getDefaultPaymentMethod(): mixed
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'defaultPaymentMethod')) {
            return null;
        }

        return $billable->defaultPaymentMethod();
    }

    /**
     * Check if the billable has a specific method.
     */
    protected function billableHasMethod(string $method): bool
    {
        $billable = $this->getBillable();

        return $billable !== null && method_exists($billable, $method);
    }

    /**
     * Get the billing panel ID from config.
     */
    protected function getBillingPanelId(): string
    {
        return (string) config('filament-cashier-chip.billing.panel_id', 'billing');
    }

    /**
     * Get a route for the billing panel.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function billingRoute(string $name, array $parameters = []): string
    {
        $panelId = $this->getBillingPanelId();

        return route("filament.{$panelId}.{$name}", $parameters);
    }
}
