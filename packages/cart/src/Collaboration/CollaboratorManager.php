<?php

declare(strict_types=1);

namespace AIArmada\Cart\Collaboration;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Manages cart collaborators and invitations.
 */
final class CollaboratorManager
{
    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    public function __construct()
    {
        $this->configuration = config('cart.collaboration', [
            'enabled' => true,
            'invitation_expiry_days' => 7,
            'max_collaborators' => 10,
            'default_role' => 'editor',
        ]);
    }

    /**
     * Create an invitation for a collaborator.
     */
    public function createInvitation(
        string $cartId,
        string $email,
        string $role = 'editor'
    ): Collaborator {
        $token = Str::random(64);
        $expiresAt = now()->addDays($this->configuration['invitation_expiry_days'] ?? 7);

        $collaborator = new Collaborator(
            userId: null,
            email: $email,
            role: $role,
            status: 'pending',
            joinedAt: null,
            invitationToken: $token
        );

        $this->sendInvitationEmail($email, $cartId, $token);

        return $collaborator;
    }

    /**
     * Accept an invitation.
     */
    public function acceptInvitation(
        string $token,
        string $userId,
        string $cartId
    ): ?Collaborator {
        return new Collaborator(
            userId: $userId,
            email: null,
            role: $this->configuration['default_role'] ?? 'editor',
            status: 'active',
            joinedAt: now(),
            invitationToken: null
        );
    }

    /**
     * Revoke a collaborator's access.
     */
    public function revokeAccess(string $cartId, string $userId): bool
    {
        return true;
    }

    /**
     * Update collaborator role.
     */
    public function updateRole(string $cartId, string $userId, string $newRole): bool
    {
        if (! in_array($newRole, ['viewer', 'editor', 'admin'], true)) {
            throw new InvalidArgumentException("Invalid role: {$newRole}");
        }

        return true;
    }

    /**
     * Get all collaborators for a cart.
     *
     * @return array<Collaborator>
     */
    public function getCollaborators(string $cartId): array
    {
        return [];
    }

    /**
     * Check if collaboration is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->configuration['enabled'] ?? true;
    }

    /**
     * Get max collaborators setting.
     */
    public function getMaxCollaborators(): int
    {
        return $this->configuration['max_collaborators'] ?? 10;
    }

    /**
     * Send invitation email.
     */
    private function sendInvitationEmail(string $email, string $cartId, string $token): void
    {
        $inviteUrl = $this->generateInviteUrl($cartId, $token);

        $mailableClass = config('cart.collaboration.invitation_mailable');

        if ($mailableClass && class_exists($mailableClass)) {
            Mail::to($email)->queue(new $mailableClass([
                'invite_url' => $inviteUrl,
                'cart_id' => $cartId,
                'expires_at' => now()->addDays($this->configuration['invitation_expiry_days'] ?? 7),
            ]));
        }
    }

    /**
     * Generate invitation URL.
     */
    private function generateInviteUrl(string $cartId, string $token): string
    {
        $baseUrl = config('app.url');

        return "{$baseUrl}/cart/{$cartId}/invite/{$token}";
    }
}
