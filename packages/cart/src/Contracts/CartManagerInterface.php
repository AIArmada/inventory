<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\Cart\Cart;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for CartManager implementations.
 *
 * This interface enables the decorator/proxy pattern for extending
 * CartManager functionality across packages (vouchers, affiliates, etc.)
 * without type conflicts when multiple packages are installed.
 */
interface CartManagerInterface
{
    /**
     * Get the current cart instance
     */
    public function getCurrentCart(): Cart;

    /**
     * Get a cart instance without changing the global state
     */
    public function getCartInstance(string $name, ?string $identifier = null): Cart;

    /**
     * Get the current instance name
     */
    public function instance(): string;

    /**
     * Set the current cart instance globally
     */
    public function setInstance(string $name): static;

    /**
     * Set the current cart identifier globally
     */
    public function setIdentifier(string $identifier): static;

    /**
     * Reset cart identifier to default (session/user ID)
     */
    public function forgetIdentifier(): static;

    /**
     * Create a new cart manager instance scoped to a specific owner
     */
    public function forOwner(Model $owner): static;

    /**
     * Get the current owner type if operating in owner-scoped mode
     */
    public function getOwnerType(): ?string;

    /**
     * Get the current owner ID if operating in owner-scoped mode
     */
    public function getOwnerId(): string | int | null;

    /**
     * Get a cart instance by its UUID
     */
    public function getById(string $uuid): ?Cart;

    /**
     * Swap cart ownership by transferring cart from old identifier to new identifier
     */
    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool;
}
