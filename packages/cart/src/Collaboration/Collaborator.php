<?php

declare(strict_types=1);

namespace AIArmada\Cart\Collaboration;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Represents a cart collaborator.
 */
final readonly class Collaborator
{
    public function __construct(
        public ?string $userId,
        public ?string $email,
        public string $role,
        public string $status,
        public ?DateTimeInterface $joinedAt = null,
        public ?string $invitationToken = null
    ) {}

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'] ?? null,
            email: $data['email'] ?? null,
            role: $data['role'] ?? 'viewer',
            status: $data['status'] ?? 'pending',
            joinedAt: isset($data['joined_at']) ? new DateTimeImmutable($data['joined_at']) : null,
            invitationToken: $data['invitation_token'] ?? null
        );
    }

    /**
     * Check if collaborator is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if collaborator can edit.
     */
    public function canEdit(): bool
    {
        return $this->role === 'editor' || $this->role === 'admin';
    }

    /**
     * Check if collaborator is pending invitation.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'joined_at' => $this->joinedAt?->format('Y-m-d H:i:s'),
            'invitation_token' => $this->invitationToken,
        ];
    }
}
