<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support\Integrations;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Exceptions\VoucherException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridge service for integrating Filament Vouchers with Filament Cart.
 *
 * This service provides:
 * - Availability detection for graceful degradation
 * - Cart URL resolution for deep linking
 * - Applied voucher retrieval
 * - Voucher application/removal helpers
 * - Cart statistics aggregation
 */
final class FilamentCartBridge
{
    private bool $available;

    private bool $warmed = false;

    /** @var class-string<Model>|null */
    private ?string $cartModel = null;

    /** @var class-string|null */
    private ?string $cartResource = null;

    public function __construct()
    {
        $this->available = class_exists(Cart::class) && class_exists(CartResource::class);

        if ($this->available) {
            $this->cartModel = Cart::class;
            $this->cartResource = CartResource::class;
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function isWarmed(): bool
    {
        return $this->warmed;
    }

    /**
     * Warm the bridge - called during Filament serving.
     *
     * This prepares integration hooks and validates the cart package is properly configured.
     */
    public function warm(): void
    {
        if ($this->warmed || ! $this->available) {
            return;
        }

        // Validate CartInstanceManager is available
        if (! class_exists(CartInstanceManager::class)) {
            Log::warning('FilamentCartBridge: CartInstanceManager not found, some features may be limited');
        }

        $this->warmed = true;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getCartModel(): ?string
    {
        return $this->cartModel;
    }

    /**
     * @return class-string|null
     */
    public function getCartResource(): ?string
    {
        return $this->cartResource;
    }

    /**
     * Resolve a cart URL from an identifier.
     */
    public function resolveCartUrl(?string $identifier): ?string
    {
        if (! $this->available || $identifier === null || $identifier === '') {
            return null;
        }

        $model = $this->getCartModel();
        $resource = $this->getCartResource();

        if (! $model || ! $resource) {
            return null;
        }

        try {
            $query = $model::query();

            if (OwnerScopedQueries::isEnabled()) {
                $query = OwnerScopedQueries::scopeVoucherLike($query);
            }

            /** @var Model|null $cart */
            $cart = $query
                ->where('identifier', $identifier)
                ->latest('created_at')
                ->first();

            if (! $cart instanceof Model) {
                return null;
            }

            return $resource::getUrl('view', ['record' => $cart]);
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to resolve cart URL', [
                'identifier' => $identifier,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get a cart by its database ID.
     */
    public function findCart(string $cartId): ?Model
    {
        if (! $this->available) {
            return null;
        }

        $model = $this->getCartModel();

        if (! $model) {
            return null;
        }

        try {
            $query = $model::query();

            if (OwnerScopedQueries::isEnabled()) {
                $query = OwnerScopedQueries::scopeVoucherLike($query);
            }

            return $query->find($cartId);
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to find cart', [
                'cart_id' => $cartId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the cart instance manager for a cart record.
     *
     * @return object|null The cart instance with voucher methods
     */
    public function getCartInstance(Model $cart): ?object
    {
        if (! $this->available || ! class_exists(CartInstanceManager::class)) {
            return null;
        }

        try {
            /** @var Cart $cart */
            return app(CartInstanceManager::class)->resolve(
                $cart->instance,
                $cart->identifier
            );
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to get cart instance', [
                'cart_id' => $cart->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get applied vouchers for a cart.
     *
     * @return Collection<int, object>
     */
    public function getAppliedVouchers(Model $cart): Collection
    {
        $instance = $this->getCartInstance($cart);

        if (! $instance) {
            return collect();
        }

        try {
            /** @phpstan-ignore-next-line - getAppliedVouchers added dynamically */
            $vouchers = $instance->getAppliedVouchers();

            return collect($vouchers);
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to get applied vouchers', [
                'cart_id' => $cart->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Apply a voucher to a cart.
     *
     * @throws VoucherException When voucher cannot be applied
     */
    public function applyVoucher(Model $cart, string $code): bool
    {
        $instance = $this->getCartInstance($cart);

        if (! $instance) {
            throw new VoucherException('Cart integration is not available');
        }

        /** @phpstan-ignore-next-line - applyVoucher added dynamically */
        $instance->applyVoucher($code);

        return true;
    }

    /**
     * Remove a voucher from a cart.
     *
     * @throws VoucherException When voucher cannot be removed
     */
    public function removeVoucher(Model $cart, string $code): bool
    {
        $instance = $this->getCartInstance($cart);

        if (! $instance) {
            throw new VoucherException('Cart integration is not available');
        }

        /** @phpstan-ignore-next-line - removeVoucher added dynamically */
        $instance->removeVoucher($code);

        return true;
    }

    /**
     * Check if a voucher is applied to a cart.
     */
    public function hasVoucher(Model $cart, string $code): bool
    {
        return $this->getAppliedVouchers($cart)
            ->contains(fn (object $voucher): bool => ($voucher->code ?? '') === $code);
    }

    /**
     * Count carts with a specific voucher applied.
     */
    public function countCartsWithVoucher(string $voucherCode): int
    {
        if (! $this->available) {
            return 0;
        }

        $model = $this->getCartModel();

        if (! $model) {
            return 0;
        }

        try {
            $escapedCode = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $voucherCode);

            /** @var Builder<Model> $query */
            $query = $model::query();

            if (OwnerScopedQueries::isEnabled()) {
                $query = OwnerScopedQueries::scopeVoucherLike($query);
            }

            return $query
                ->whereNotNull('conditions')
                ->where(function ($q) use ($voucherCode, $escapedCode): void {
                    $q->whereJsonContains('conditions', ['voucher' => $voucherCode])
                        ->orWhereRaw('conditions LIKE ?', ['%"code":"' . $escapedCode . '"%']);
                })
                ->count();
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to count carts with voucher', [
                'voucher_code' => $voucherCode,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get aggregate statistics for carts with vouchers.
     *
     * @return array{active_carts_with_vouchers: int, total_potential_discount: int}
     */
    public function getVoucherCartStats(): array
    {
        if (! $this->available) {
            return [
                'active_carts_with_vouchers' => 0,
                'total_potential_discount' => 0,
            ];
        }

        $model = $this->getCartModel();

        if (! $model) {
            return [
                'active_carts_with_vouchers' => 0,
                'total_potential_discount' => 0,
            ];
        }

        try {
            /** @var Builder<Model> $query */
            $query = $model::query();

            if (OwnerScopedQueries::isEnabled()) {
                $query = OwnerScopedQueries::scopeVoucherLike($query);
            }

            $cartsWithVouchers = $query
                ->whereNotNull('conditions')
                ->whereRaw("conditions LIKE '%voucher%'")
                ->count();

            return [
                'active_carts_with_vouchers' => $cartsWithVouchers,
                'total_potential_discount' => 0, // Would require iterating carts
            ];
        } catch (Throwable $exception) {
            Log::debug('FilamentCartBridge: Failed to get voucher cart stats', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'active_carts_with_vouchers' => 0,
                'total_potential_discount' => 0,
            ];
        }
    }
}
