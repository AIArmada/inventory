<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Support\Integrations;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart as FilamentCart;
use AIArmada\FilamentCart\Resources\CartResource;
use Illuminate\Database\Eloquent\Model;

final class CartBridge
{
    private bool $available;

    public function __construct()
    {
        $this->available = class_exists(FilamentCart::class) && class_exists(CartResource::class);
    }

    public function warm(): void
    {
        // reserved for future runtime hooks
    }

    public function isAvailable(): bool
    {
        return $this->available && (bool) config('filament-affiliates.integrations.filament_cart', true);
    }

    public function resolveUrl(?string $identifier, ?string $instance = null): ?string
    {
        if (! $this->isAvailable() || ! $identifier) {
            return null;
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            /** @var Model|null $owner */
            $owner = OwnerContext::resolve();

            $hasReference = AffiliateConversion::query()
                ->forOwner($owner, false)
                ->where('cart_identifier', $identifier)
                ->when(
                    $instance !== null,
                    fn ($query) => $query->where('cart_instance', $instance),
                )
                ->exists();

            if (! $hasReference) {
                return null;
            }
        }

        /** @var \Illuminate\Database\Eloquent\Builder<FilamentCart> $cartQuery */
        $cartQuery = CartResource::getEloquentQuery()->where('identifier', $identifier);

        if ((bool) config('affiliates.owner.enabled', false)) {
            /** @var Model|null $owner */
            $owner = OwnerContext::resolve();
            $cartQuery->forOwner($owner, false);
        }

        if ($instance) {
            $cartQuery->where('instance', $instance);
        }

        /** @var FilamentCart|null $cart */
        $cart = $cartQuery->latest('created_at')->first();

        if (! $cart) {
            return null;
        }

        if (method_exists(CartResource::class, 'canView') && ! CartResource::canView($cart)) {
            return null;
        }

        return CartResource::getUrl('view', ['record' => $cart]);
    }
}
