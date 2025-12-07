<?php

declare(strict_types=1);

namespace AIArmada\Cart\Blockchain;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Generates cryptographic proofs for cart state using Merkle trees.
 */
final class CartProofGenerator
{
    /**
     * Generate a complete proof for a cart's current state.
     *
     * @return array{
     *     cart_id: string,
     *     root_hash: string,
     *     item_hashes: array<string, string>,
     *     merkle_tree: array<string>,
     *     metadata: array<string, mixed>,
     *     timestamp: string,
     *     signature: string
     * }
     */
    public function generateProof(Cart $cart): array
    {
        $items = $cart->getItems();
        $itemHashes = $this->hashItems($items);
        $merkleTree = $this->buildMerkleTree($itemHashes);
        $rootHash = $merkleTree[0] ?? hash('sha256', $cart->getIdentifier());

        $metadata = [
            'item_count' => $items->count(),
            'total_quantity' => $items->sum(fn (CartItem $item) => $item->quantity),
            'cart_identifier' => $cart->getIdentifier(),
            'instance' => $cart->instance(),
            'version' => 1,
        ];

        $signature = $this->sign($rootHash, $metadata);

        return [
            'cart_id' => $cart->getIdentifier(),
            'root_hash' => $rootHash,
            'item_hashes' => $itemHashes,
            'merkle_tree' => $merkleTree,
            'metadata' => $metadata,
            'timestamp' => now()->toIso8601String(),
            'signature' => $signature,
        ];
    }

    /**
     * Generate an inclusion proof for a specific item.
     *
     * @return array{
     *     item_id: string,
     *     item_hash: string,
     *     proof_path: array<array{hash: string, position: string}>,
     *     root_hash: string
     * }
     */
    public function generateItemProof(Cart $cart, string $itemId): array
    {
        $items = $cart->getItems();
        $itemHashes = $this->hashItems($items);
        $merkleTree = $this->buildMerkleTree($itemHashes);

        $itemHash = $itemHashes[$itemId] ?? null;

        if (! $itemHash) {
            throw new InvalidArgumentException("Item {$itemId} not found in cart");
        }

        $proofPath = $this->generateProofPath($itemHash, $itemHashes, $merkleTree);

        return [
            'item_id' => $itemId,
            'item_hash' => $itemHash,
            'proof_path' => $proofPath,
            'root_hash' => $merkleTree[0] ?? $itemHash,
        ];
    }

    /**
     * Generate a compact hash for quick verification.
     */
    public function generateCompactHash(Cart $cart): string
    {
        $items = $cart->getItems();
        $itemHashes = $this->hashItems($items);
        $merkleTree = $this->buildMerkleTree($itemHashes);

        return $merkleTree[0] ?? hash('sha256', $cart->getIdentifier());
    }

    /**
     * Hash individual cart items.
     *
     * @param  Collection<int, CartItem>  $items
     * @return array<string, string>
     */
    private function hashItems(Collection $items): array
    {
        $hashes = [];

        foreach ($items as $item) {
            $data = json_encode([
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'attributes' => $item->attributes->toArray(),
            ], JSON_THROW_ON_ERROR);

            $hashes[$item->id] = hash('sha256', $data);
        }

        return $hashes;
    }

    /**
     * Build a Merkle tree from leaf hashes.
     *
     * @param  array<string, string>  $leafHashes
     * @return array<string>
     */
    private function buildMerkleTree(array $leafHashes): array
    {
        if (empty($leafHashes)) {
            return [];
        }

        $leaves = array_values($leafHashes);

        if (count($leaves) % 2 !== 0) {
            $leaves[] = $leaves[count($leaves) - 1];
        }

        $tree = $leaves;
        $currentLevel = $leaves;

        while (count($currentLevel) > 1) {
            $nextLevel = [];

            for ($i = 0; $i < count($currentLevel); $i += 2) {
                $left = $currentLevel[$i];
                $right = $currentLevel[$i + 1] ?? $left;
                $combined = hash('sha256', $left.$right);
                $nextLevel[] = $combined;
            }

            array_unshift($tree, ...$nextLevel);
            $currentLevel = $nextLevel;
        }

        return $tree;
    }

    /**
     * Generate proof path for a specific item.
     *
     * @param  array<string, string>  $itemHashes
     * @param  array<string>  $merkleTree
     * @return array<array{hash: string, position: string}>
     */
    private function generateProofPath(string $itemHash, array $itemHashes, array $merkleTree): array
    {
        $leaves = array_values($itemHashes);
        $index = array_search($itemHash, $leaves, true);

        if ($index === false) {
            return [];
        }

        $proofPath = [];
        $treeOffset = count($merkleTree) - count($leaves);
        $currentIndex = $index;
        $levelSize = count($leaves);

        while ($levelSize > 1) {
            $isRightNode = $currentIndex % 2 === 1;
            $siblingIndex = $isRightNode ? $currentIndex - 1 : $currentIndex + 1;

            if ($siblingIndex < $levelSize) {
                $siblingHash = $merkleTree[$treeOffset + $siblingIndex] ?? null;

                if ($siblingHash) {
                    $proofPath[] = [
                        'hash' => $siblingHash,
                        'position' => $isRightNode ? 'left' : 'right',
                    ];
                }
            }

            $currentIndex = (int) floor($currentIndex / 2);
            $levelSize = (int) ceil($levelSize / 2);
            $treeOffset -= $levelSize;
        }

        return $proofPath;
    }

    /**
     * Sign the proof with HMAC.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function sign(string $rootHash, array $metadata): string
    {
        $key = config('cart.blockchain.signing_key', config('app.key'));
        $data = $rootHash.json_encode($metadata, JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $data, $key);
    }
}
