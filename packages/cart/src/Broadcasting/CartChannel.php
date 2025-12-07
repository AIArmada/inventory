<?php

declare(strict_types=1);

namespace AIArmada\Cart\Broadcasting;

use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;

/**
 * Broadcasting channel for real-time cart collaboration.
 */
final class CartChannel
{
    public function __construct(
        private readonly StorageInterface $storage
    ) {}

    /**
     * Get the channel instance for a cart.
     */
    public static function channelFor(string $cartId): PresenceChannel
    {
        return new PresenceChannel("cart.{$cartId}");
    }

    /**
     * Get the private channel for a cart.
     */
    public static function privateChannelFor(string $cartId): Channel
    {
        return new Channel("cart.{$cartId}");
    }

    /**
     * Broadcast an item added event.
     *
     * @param  array<string, mixed>  $itemData
     */
    public static function broadcastItemAdded(string $cartId, array $itemData): void
    {
        broadcast(new Events\CartItemAdded($cartId, $itemData))->toOthers();
    }

    /**
     * Broadcast an item updated event.
     *
     * @param  array<string, mixed>  $itemData
     */
    public static function broadcastItemUpdated(string $cartId, array $itemData): void
    {
        broadcast(new Events\CartItemUpdated($cartId, $itemData))->toOthers();
    }

    /**
     * Broadcast an item removed event.
     */
    public static function broadcastItemRemoved(string $cartId, string $itemId): void
    {
        broadcast(new Events\CartItemRemoved($cartId, $itemId))->toOthers();
    }

    /**
     * Broadcast a cart sync event with full state.
     *
     * @param  array<string, mixed>  $cartState
     */
    public static function broadcastSync(string $cartId, array $cartState): void
    {
        broadcast(new Events\CartSynced($cartId, $cartState))->toOthers();
    }

    /**
     * Broadcast when a collaborator joins.
     *
     * @param  array<string, mixed>  $collaborator
     */
    public static function broadcastCollaboratorJoined(string $cartId, array $collaborator): void
    {
        broadcast(new Events\CollaboratorJoined($cartId, $collaborator));
    }

    /**
     * Broadcast when a collaborator leaves.
     */
    public static function broadcastCollaboratorLeft(string $cartId, string $userId): void
    {
        broadcast(new Events\CollaboratorLeft($cartId, $userId));
    }

    /**
     * Authenticate the user's access to the cart channel.
     *
     * @return array<string, mixed>|false
     */
    public function join(mixed $user, string $cartId): array|false
    {
        $cartData = $this->getCartData($cartId);

        if (! $cartData) {
            return false;
        }

        if (! ($cartData['is_collaborative'] ?? false)) {
            return false;
        }

        if ($this->canAccessCart($user, $cartData)) {
            return [
                'id' => $user->id,
                'name' => $user->name ?? 'Anonymous',
                'email' => $user->email ?? null,
                'role' => $this->getUserRole($user, $cartData),
            ];
        }

        return false;
    }

    /**
     * Get cart data from storage.
     *
     * @return array<string, mixed>|null
     */
    private function getCartData(string $cartId): ?array
    {
        $metadata = $this->storage->getAllMetadata($cartId, 'default');

        if (empty($metadata)) {
            return null;
        }

        return [
            'id' => $cartId,
            'is_collaborative' => $metadata['is_collaborative'] ?? false,
            'owner_user_id' => $metadata['owner_user_id'] ?? null,
            'collaborators' => $metadata['collaborators'] ?? [],
        ];
    }

    /**
     * Check if user can access the cart.
     *
     * @param  array<string, mixed>  $cartData
     */
    private function canAccessCart(mixed $user, array $cartData): bool
    {
        if (($cartData['owner_user_id'] ?? null) === $user->id) {
            return true;
        }

        $collaborators = $cartData['collaborators'] ?? [];

        foreach ($collaborators as $collaborator) {
            if (($collaborator['user_id'] ?? null) === $user->id && ($collaborator['status'] ?? '') === 'active') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user's role in the cart.
     *
     * @param  array<string, mixed>  $cartData
     */
    private function getUserRole(mixed $user, array $cartData): string
    {
        if (($cartData['owner_user_id'] ?? null) === $user->id) {
            return 'owner';
        }

        $collaborators = $cartData['collaborators'] ?? [];

        foreach ($collaborators as $collaborator) {
            if (($collaborator['user_id'] ?? null) === $user->id) {
                return $collaborator['role'] ?? 'viewer';
            }
        }

        return 'viewer';
    }
}
