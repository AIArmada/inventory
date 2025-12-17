<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Listeners;

use AIArmada\Cart\Events\CartMerged;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use Throwable;

/**
 * Update cart snapshot identifier when carts are merged.
 *
 * When a guest cart is merged/swapped to a user cart, the source cart's
 * snapshot identifier is updated from the guest session ID to the user ID.
 * The CartMerged event provides the original source identifier (before the swap)
 * so we can update the correct snapshot.
 *
 * If the target user already has a cart snapshot, the source snapshot items
 * and conditions are transferred to the target snapshot, then the source is deleted.
 * Otherwise, the source snapshot identifier is simply updated to the user ID.
 */
final class CleanupSnapshotOnCartMerged
{
    public function handle(CartMerged $event): void
    {
        try {
            // Use the original source identifier if provided (this is the guest session)
            // After a swap, both carts have the same identifier, so we need the original
            $sourceIdentifier = $event->originalSourceIdentifier ?? $event->sourceCart->getIdentifier();
            $targetIdentifier = $event->originalTargetIdentifier ?? $event->targetCart->getIdentifier();
            $instance = $event->sourceCart->instance();

            $owner = CartSnapshot::resolveCurrentOwner();
            $targetOwnerKey = CartSnapshot::resolveOwnerKey($owner);

            // Find the source cart snapshot (guest cart)
            $sourceSnapshot = CartSnapshot::query()
                ->where('identifier', $sourceIdentifier)
                ->where('instance', $instance)
                ->when(CartSnapshot::ownerScopingEnabled(), fn ($q) => $q->where('owner_key', 'global'))
                ->first();

            if (! $sourceSnapshot) {
                return;
            }

            // Check if target user already has a cart snapshot
            $targetSnapshot = CartSnapshot::query()
                ->where('identifier', $targetIdentifier)
                ->where('instance', $instance)
                ->when(CartSnapshot::ownerScopingEnabled(), fn ($q) => $q->where('owner_key', $targetOwnerKey))
                ->first();

            if ($targetSnapshot) {
                // Target snapshot exists, transfer items and conditions then delete source
                CartItem::where('cart_id', $sourceSnapshot->id)
                    ->update(['cart_id' => $targetSnapshot->id]);

                CartCondition::where('cart_id', $sourceSnapshot->id)
                    ->update(['cart_id' => $targetSnapshot->id]);

                // Delete the source snapshot
                $sourceSnapshot->delete();
            } else {
                // No target snapshot exists, simply update the identifier
                $sourceSnapshot->identifier = $targetIdentifier;

                if (CartSnapshot::ownerScopingEnabled() && $owner !== null) {
                    $sourceSnapshot->assignOwner($owner);
                }

                $sourceSnapshot->save();
            }

            // Note: The target cart snapshot data (totals, metadata, etc.) will be updated
            // by the normal cart sync listeners (SyncCompleteCart, etc.) after this event.
        } catch (Throwable $e) {
            // Silently fail to not break the cart merge process
            // The snapshot will be cleaned up on next sync
        }
    }
}
