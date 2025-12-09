<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Models\Delegation;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Collection;

class DelegationService
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Check if user can delegate a permission.
     */
    public function canDelegate(mixed $delegator, string $permission): bool
    {
        // User must have the permission themselves
        if (! method_exists($delegator, 'can') || ! $delegator->can($permission)) {
            return false;
        }

        // User must have delegation rights
        if (method_exists($delegator, 'can')) {
            return $delegator->can("delegate.{$permission}")
                || $delegator->can('delegate.*');
        }

        return false;
    }

    /**
     * Delegate a permission to another user.
     */
    public function delegate(
        mixed $delegator,
        mixed $delegatee,
        string $permission,
        ?DateTimeInterface $expiresAt = null,
        bool $canRedelegate = false
    ): Delegation {
        if (! $this->canDelegate($delegator, $permission)) {
            throw new CannotDelegateException("Cannot delegate {$permission}");
        }

        $delegation = Delegation::create([
            'delegator_id' => $delegator->id,
            'delegatee_id' => $delegatee->id,
            'permission' => $permission,
            'expires_at' => $expiresAt,
            'can_redelegate' => $canRedelegate,
        ]);

        // Grant the delegated permission
        if (method_exists($delegatee, 'givePermissionTo')) {
            $delegatee->givePermissionTo($permission);
        }

        // If delegation rights are also granted
        if ($canRedelegate && method_exists($delegatee, 'givePermissionTo')) {
            $delegatee->givePermissionTo("delegate.{$permission}");
        }

        $this->auditLogger->log(
            eventType: AuditEventType::PermissionDelegated,
            metadata: [
                'delegator' => $delegator->id,
                'delegatee' => $delegatee->id,
                'permission' => $permission,
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
                'can_redelegate' => $canRedelegate,
            ]
        );

        return $delegation;
    }

    /**
     * Revoke a delegation.
     */
    public function revoke(Delegation $delegation): void
    {
        // Revoke the permission from delegatee
        if (method_exists($delegation->delegatee, 'revokePermissionTo')) {
            $delegation->delegatee->revokePermissionTo($delegation->permission);
        }

        // Revoke delegation rights if granted
        if ($delegation->can_redelegate && method_exists($delegation->delegatee, 'revokePermissionTo')) {
            $delegation->delegatee->revokePermissionTo("delegate.{$delegation->permission}");
        }

        // Cascade: revoke any sub-delegations
        $subDelegations = Delegation::where('delegator_id', $delegation->delegatee_id)
            ->where('permission', $delegation->permission)
            ->whereNull('revoked_at')
            ->get();

        foreach ($subDelegations as $subDelegation) {
            $this->revoke($subDelegation);
        }

        $delegation->revoke();

        $this->auditLogger->log(
            eventType: AuditEventType::PermissionDelegationRevoked,
            metadata: [
                'delegation_id' => $delegation->id,
                'delegator' => $delegation->delegator_id,
                'delegatee' => $delegation->delegatee_id,
                'permission' => $delegation->permission,
            ]
        );
    }

    /**
     * Get all active delegations for a user.
     *
     * @return Collection<int, Delegation>
     */
    public function getDelegationsFor(mixed $user): Collection
    {
        return Delegation::where('delegatee_id', $user->id)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    /**
     * Get all delegations made by a user.
     *
     * @return Collection<int, Delegation>
     */
    public function getDelegationsBy(mixed $user): Collection
    {
        return Delegation::where('delegator_id', $user->id)
            ->whereNull('revoked_at')
            ->get();
    }

    /**
     * Check if a permission was delegated to the user.
     */
    public function hasDelegatedPermission(mixed $user, string $permission): bool
    {
        return Delegation::where('delegatee_id', $user->id)
            ->where('permission', $permission)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Cleanup expired delegations.
     */
    public function cleanupExpired(): int
    {
        $expired = Delegation::whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $delegation) {
            $this->revoke($delegation);
            $count++;
        }

        return $count;
    }

    /**
     * Get the delegation chain for a permission.
     *
     * @return Collection<int, Delegation>
     */
    public function getDelegationChain(Delegation $delegation): Collection
    {
        $chain = collect([$delegation]);

        // Find parent delegations
        $current = $delegation;
        while ($parent = $this->findParentDelegation($current)) {
            $chain->prepend($parent);
            $current = $parent;
        }

        // Find child delegations
        $children = $this->findChildDelegations($delegation);
        $chain = $chain->merge($children);

        return $chain;
    }

    /**
     * Find the parent delegation (who delegated to the delegator).
     */
    protected function findParentDelegation(Delegation $delegation): ?Delegation
    {
        return Delegation::where('delegatee_id', $delegation->delegator_id)
            ->where('permission', $delegation->permission)
            ->whereNull('revoked_at')
            ->first();
    }

    /**
     * Find child delegations (who the delegatee delegated to).
     *
     * @return Collection<int, Delegation>
     */
    protected function findChildDelegations(Delegation $delegation): Collection
    {
        $children = collect();

        $directChildren = Delegation::where('delegator_id', $delegation->delegatee_id)
            ->where('permission', $delegation->permission)
            ->whereNull('revoked_at')
            ->get();

        foreach ($directChildren as $child) {
            $children->push($child);
            $children = $children->merge($this->findChildDelegations($child));
        }

        return $children;
    }
}

/**
 * Exception for delegation errors.
 */
class CannotDelegateException extends Exception {}
