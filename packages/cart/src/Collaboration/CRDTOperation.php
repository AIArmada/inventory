<?php

declare(strict_types=1);

namespace AIArmada\Cart\Collaboration;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Represents a CRDT operation for cart synchronization.
 */
final readonly class CRDTOperation
{
    /**
     * @param  string  $id  Unique operation ID
     * @param  string  $type  Operation type: add, update, remove
     * @param  string  $cartId  Cart ID
     * @param  string  $userId  User who performed the operation
     * @param  string  $itemId  Item being operated on
     * @param  array<string, mixed>  $data  Operation data
     * @param  array<string, int>  $vectorClock  Vector clock at time of operation
     * @param  DateTimeInterface  $timestamp  When operation occurred
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $cartId,
        public string $userId,
        public string $itemId,
        public array $data,
        public array $vectorClock,
        public DateTimeInterface $timestamp
    ) {}

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            cartId: $data['cart_id'],
            userId: $data['user_id'],
            itemId: $data['item_id'],
            data: $data['data'] ?? [],
            vectorClock: $data['vector_clock'] ?? [],
            timestamp: new DateTimeImmutable($data['timestamp'])
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'cart_id' => $this->cartId,
            'user_id' => $this->userId,
            'item_id' => $this->itemId,
            'data' => $this->data,
            'vector_clock' => $this->vectorClock,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
        ];
    }
}
