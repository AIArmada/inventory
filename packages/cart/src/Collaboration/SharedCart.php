<?php

declare(strict_types=1);

namespace AIArmada\Cart\Collaboration;

use AIArmada\Cart\Broadcasting\CartChannel;
use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared cart implementation for collaborative shopping.
 *
 * Enables multiple users to view and edit the same cart in real-time.
 */
final class SharedCart
{
    private Cart $cart;

    private bool $isCollaborative = false;

    private ?string $ownerUserId = null;

    /**
     * @var array<Collaborator>
     */
    private array $collaborators = [];

    private string $collaborationMode = 'edit';

    private ?string $shareToken = null;

    private ?DateTimeInterface $shareExpiresAt = null;

    public function __construct(
        private readonly CartManager $cartManager,
        private readonly CollaboratorManager $collaboratorManager,
        private readonly CartCRDT $crdt
    ) {}

    /**
     * Create a shared cart from an existing cart.
     */
    public static function fromCart(Cart $cart): self
    {
        $instance = app(self::class);
        $instance->cart = $cart;
        $instance->loadCollaborationState();

        return $instance;
    }

    /**
     * Enable collaboration on this cart.
     */
    public function enableCollaboration(Authenticatable $owner): self
    {
        $this->isCollaborative = true;
        $this->ownerUserId = (string) $owner->getAuthIdentifier();
        $this->shareToken = Str::random(64);
        $this->shareExpiresAt = now()->addDays(7);

        $this->saveCollaborationState();

        return $this;
    }

    /**
     * Disable collaboration.
     */
    public function disableCollaboration(): self
    {
        $this->isCollaborative = false;
        $this->collaborators = [];
        $this->shareToken = null;
        $this->shareExpiresAt = null;

        $this->saveCollaborationState();
        $this->broadcastUpdate('collaboration_disabled');

        return $this;
    }

    /**
     * Add a collaborator via email invitation.
     */
    public function invite(string $email, string $role = 'editor'): Collaborator
    {
        $this->ensureCollaborative();
        $this->checkCollaboratorLimit();

        $collaborator = $this->collaboratorManager->createInvitation(
            cartId: $this->cart->getIdentifier(),
            email: $email,
            role: $role
        );

        $this->collaborators[] = $collaborator;
        $this->saveCollaborationState();

        return $collaborator;
    }

    /**
     * Add a collaborator by user ID.
     */
    public function addCollaborator(string $userId, string $role = 'editor'): Collaborator
    {
        $this->ensureCollaborative();
        $this->checkCollaboratorLimit();

        $collaborator = new Collaborator(
            userId: $userId,
            email: null,
            role: $role,
            status: 'active',
            joinedAt: now()
        );

        $this->collaborators[] = $collaborator;
        $this->saveCollaborationState();
        $this->broadcastUpdate('collaborator_added', ['user_id' => $userId]);

        return $collaborator;
    }

    /**
     * Remove a collaborator.
     */
    public function removeCollaborator(string $userId): bool
    {
        $this->ensureCollaborative();

        $initialCount = count($this->collaborators);
        $this->collaborators = array_filter(
            $this->collaborators,
            fn (Collaborator $c) => $c->userId !== $userId
        );

        if (count($this->collaborators) < $initialCount) {
            $this->saveCollaborationState();
            $this->broadcastUpdate('collaborator_removed', ['user_id' => $userId]);

            return true;
        }

        return false;
    }

    /**
     * Join a shared cart using share token.
     */
    public function joinWithToken(string $token, Authenticatable $user): bool
    {
        if ($this->shareToken !== $token) {
            return false;
        }

        if ($this->shareExpiresAt && now()->isAfter($this->shareExpiresAt)) {
            return false;
        }

        $userId = (string) $user->getAuthIdentifier();

        if ($this->hasCollaborator($userId)) {
            return true;
        }

        $this->addCollaborator($userId, 'viewer');

        return true;
    }

    /**
     * Generate a shareable link.
     */
    public function getShareLink(): string
    {
        $this->ensureCollaborative();

        if (! $this->shareToken) {
            $this->shareToken = Str::random(64);
            $this->shareExpiresAt = now()->addDays(7);
            $this->saveCollaborationState();
        }

        $baseUrl = config('app.url');

        return "{$baseUrl}/cart/join/{$this->shareToken}";
    }

    /**
     * Regenerate share token.
     */
    public function regenerateShareToken(): string
    {
        $this->ensureCollaborative();

        $this->shareToken = Str::random(64);
        $this->shareExpiresAt = now()->addDays(7);
        $this->saveCollaborationState();

        return $this->shareToken;
    }

    /**
     * Add item with CRDT conflict resolution.
     */
    public function addItem(
        string $userId,
        string $itemId,
        string $name,
        int $priceInCents,
        int $quantity = 1,
        array $attributes = []
    ): void {
        $this->ensureCanEdit($userId);

        $operation = $this->crdt->createAddOperation(
            cartId: $this->cart->getIdentifier(),
            userId: $userId,
            itemId: $itemId,
            data: [
                'name' => $name,
                'price' => $priceInCents,
                'quantity' => $quantity,
                'attributes' => $attributes,
            ]
        );

        $this->crdt->apply($operation);
        $this->cart->add($itemId, $name, $priceInCents, $quantity, $attributes);

        $this->broadcastUpdate('item_added', [
            'user_id' => $userId,
            'item_id' => $itemId,
            'item_name' => $name,
        ]);
    }

    /**
     * Update item quantity with CRDT.
     */
    public function updateQuantity(string $userId, string $itemId, int $quantity): void
    {
        $this->ensureCanEdit($userId);

        $operation = $this->crdt->createUpdateOperation(
            cartId: $this->cart->getIdentifier(),
            userId: $userId,
            itemId: $itemId,
            data: ['quantity' => $quantity]
        );

        $this->crdt->apply($operation);
        $this->cart->update($itemId, ['quantity' => $quantity]);

        $this->broadcastUpdate('item_updated', [
            'user_id' => $userId,
            'item_id' => $itemId,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Remove item with CRDT.
     */
    public function removeItem(string $userId, string $itemId): void
    {
        $this->ensureCanEdit($userId);

        $operation = $this->crdt->createRemoveOperation(
            cartId: $this->cart->getIdentifier(),
            userId: $userId,
            itemId: $itemId
        );

        $this->crdt->apply($operation);
        $this->cart->remove($itemId);

        $this->broadcastUpdate('item_removed', [
            'user_id' => $userId,
            'item_id' => $itemId,
        ]);
    }

    /**
     * Get all collaborators.
     *
     * @return array<Collaborator>
     */
    public function getCollaborators(): array
    {
        return $this->collaborators;
    }

    /**
     * Check if user is a collaborator.
     */
    public function hasCollaborator(string $userId): bool
    {
        if ($this->ownerUserId === $userId) {
            return true;
        }

        foreach ($this->collaborators as $collaborator) {
            if ($collaborator->userId === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is the owner.
     */
    public function isOwner(string $userId): bool
    {
        return $this->ownerUserId === $userId;
    }

    /**
     * Get the underlying cart.
     */
    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * Check if collaboration is enabled.
     */
    public function isCollaborative(): bool
    {
        return $this->isCollaborative;
    }

    /**
     * Get active collaborator count.
     */
    public function getActiveCollaboratorCount(): int
    {
        return count(array_filter(
            $this->collaborators,
            fn (Collaborator $c) => $c->status === 'active'
        ));
    }

    /**
     * Set maximum collaborators.
     */
    public function setMaxCollaborators(int $max): self
    {
        $this->ensureCollaborative();

        $cartsTable = config('cart.database.table', 'carts');
        DB::table($cartsTable)
            ->where('id', $this->cart->getIdentifier())
            ->update(['max_collaborators' => $max]);

        return $this;
    }

    /**
     * Set collaboration mode.
     */
    public function setCollaborationMode(string $mode): self
    {
        $this->ensureCollaborative();

        if (! in_array($mode, ['view', 'edit'], true)) {
            throw new InvalidArgumentException("Invalid collaboration mode: {$mode}");
        }

        $this->collaborationMode = $mode;
        $this->saveCollaborationState();

        return $this;
    }

    /**
     * Ensure cart is collaborative.
     */
    private function ensureCollaborative(): void
    {
        if (! $this->isCollaborative) {
            throw new RuntimeException('Cart is not collaborative');
        }
    }

    /**
     * Ensure user can edit.
     */
    private function ensureCanEdit(string $userId): void
    {
        if (! $this->hasCollaborator($userId)) {
            throw new RuntimeException('User is not a collaborator');
        }

        if ($this->isOwner($userId)) {
            return;
        }

        foreach ($this->collaborators as $collaborator) {
            if ($collaborator->userId === $userId) {
                if ($collaborator->role === 'viewer') {
                    throw new RuntimeException('User does not have edit permissions');
                }

                return;
            }
        }
    }

    /**
     * Check collaborator limit.
     */
    private function checkCollaboratorLimit(): void
    {
        $cartsTable = config('cart.database.table', 'carts');
        $maxCollaborators = DB::table($cartsTable)
            ->where('id', $this->cart->getIdentifier())
            ->value('max_collaborators') ?? 5;

        if (count($this->collaborators) >= $maxCollaborators) {
            throw new RuntimeException("Maximum collaborators ({$maxCollaborators}) reached");
        }
    }

    /**
     * Load collaboration state from database.
     */
    private function loadCollaborationState(): void
    {
        $cartsTable = config('cart.database.table', 'carts');
        $record = DB::table($cartsTable)
            ->where('id', $this->cart->getIdentifier())
            ->first();

        if (! $record) {
            return;
        }

        $this->isCollaborative = (bool) ($record->is_collaborative ?? false);
        $this->ownerUserId = $record->owner_user_id ?? null;
        $this->collaborationMode = $record->collaboration_mode ?? 'edit';
        $this->shareToken = $record->share_token ?? null;
        $this->shareExpiresAt = $record->share_expires_at ? new DateTimeImmutable($record->share_expires_at) : null;

        $collaboratorsJson = $record->collaborators ?? '[]';
        $collaboratorsData = json_decode($collaboratorsJson, true) ?: [];

        $this->collaborators = array_map(
            fn (array $c) => Collaborator::fromArray($c),
            $collaboratorsData
        );
    }

    /**
     * Save collaboration state to database.
     */
    private function saveCollaborationState(): void
    {
        $cartsTable = config('cart.database.table', 'carts');

        DB::table($cartsTable)
            ->where('id', $this->cart->getIdentifier())
            ->update([
                'is_collaborative' => $this->isCollaborative,
                'owner_user_id' => $this->ownerUserId,
                'collaborators' => json_encode(array_map(
                    fn (Collaborator $c) => $c->toArray(),
                    $this->collaborators
                )),
                'collaboration_mode' => $this->collaborationMode,
                'share_token' => $this->shareToken,
                'share_expires_at' => $this->shareExpiresAt,
                'updated_at' => now(),
            ]);

        Cache::forget("cart:collaboration:{$this->cart->getIdentifier()}");
    }

    /**
     * Broadcast update to all collaborators.
     *
     * @param  array<string, mixed>  $data
     */
    private function broadcastUpdate(string $event, array $data = []): void
    {
        $cartId = $this->cart->getIdentifier();
        $payload = array_merge($data, [
            'cart_id' => $cartId,
            'timestamp' => now()->toIso8601String(),
        ]);

        CartChannel::broadcastSync($cartId, $payload);
    }
}
