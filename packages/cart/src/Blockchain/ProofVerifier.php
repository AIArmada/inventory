<?php

declare(strict_types=1);

namespace AIArmada\Cart\Blockchain;

use AIArmada\Cart\Cart;
use Throwable;

/**
 * Verifies cart proofs and blockchain anchors.
 */
final class ProofVerifier
{
    public function __construct(
        private readonly CartProofGenerator $proofGenerator,
        private readonly ChainAnchor $chainAnchor
    ) {}

    /**
     * Verify a complete cart proof.
     *
     * @param array{
     *     cart_id: string,
     *     root_hash: string,
     *     item_hashes: array<string, string>,
     *     merkle_tree: array<string>,
     *     metadata: array<string, mixed>,
     *     timestamp: string,
     *     signature: string
     * } $proof
     * @return array{
     *     valid: bool,
     *     checks: array<string, bool>,
     *     errors: array<string>,
     *     verified_at: string
     * }
     */
    public function verifyProof(array $proof): array
    {
        $checks = [];
        $errors = [];

        $checks['signature_valid'] = $this->verifySignature($proof);
        if (! $checks['signature_valid']) {
            $errors[] = 'Proof signature is invalid';
        }

        $checks['merkle_valid'] = $this->verifyMerkleTree(
            $proof['item_hashes'],
            $proof['merkle_tree'],
            $proof['root_hash']
        );
        if (! $checks['merkle_valid']) {
            $errors[] = 'Merkle tree verification failed';
        }

        $checks['metadata_valid'] = $this->verifyMetadata($proof['metadata']);
        if (! $checks['metadata_valid']) {
            $errors[] = 'Metadata validation failed';
        }

        $checks['timestamp_valid'] = $this->verifyTimestamp($proof['timestamp']);
        if (! $checks['timestamp_valid']) {
            $errors[] = 'Timestamp is invalid or in the future';
        }

        return [
            'valid' => empty($errors),
            'checks' => $checks,
            'errors' => $errors,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Verify an item inclusion proof.
     *
     * @param array{
     *     item_id: string,
     *     item_hash: string,
     *     proof_path: array<array{hash: string, position: string}>,
     *     root_hash: string
     * } $itemProof
     */
    public function verifyItemProof(array $itemProof): bool
    {
        $currentHash = $itemProof['item_hash'];

        foreach ($itemProof['proof_path'] as $step) {
            if ($step['position'] === 'left') {
                $currentHash = hash('sha256', $step['hash'].$currentHash);
            } else {
                $currentHash = hash('sha256', $currentHash.$step['hash']);
            }
        }

        return $currentHash === $itemProof['root_hash'];
    }

    /**
     * Verify that current cart state matches a stored proof.
     *
     * @param array{
     *     cart_id: string,
     *     root_hash: string,
     *     item_hashes: array<string, string>,
     *     merkle_tree: array<string>,
     *     metadata: array<string, mixed>,
     *     timestamp: string,
     *     signature: string
     * } $storedProof
     * @return array{
     *     matches: bool,
     *     current_hash: string,
     *     stored_hash: string,
     *     differences: array<string>
     * }
     */
    public function verifyCartIntegrity(Cart $cart, array $storedProof): array
    {
        $currentHash = $this->proofGenerator->generateCompactHash($cart);
        $storedHash = $storedProof['root_hash'];
        $matches = $currentHash === $storedHash;

        $differences = [];

        if (! $matches) {
            $currentProof = $this->proofGenerator->generateProof($cart);
            $differences = $this->findDifferences(
                $storedProof['item_hashes'],
                $currentProof['item_hashes']
            );
        }

        return [
            'matches' => $matches,
            'current_hash' => $currentHash,
            'stored_hash' => $storedHash,
            'differences' => $differences,
        ];
    }

    /**
     * Verify a blockchain anchor.
     *
     * @return array{
     *     anchored: bool,
     *     chain_verification: array{
     *         verified: bool,
     *         anchor_time: string|null,
     *         block_number: int|null,
     *         confirmations: int|null
     *     },
     *     proof_valid: bool
     * }
     */
    public function verifyAnchor(string $anchorId, string $rootHash): array
    {
        $chainVerification = $this->chainAnchor->verify($anchorId);

        return [
            'anchored' => $chainVerification['verified'],
            'chain_verification' => $chainVerification,
            'proof_valid' => $chainVerification['verified'],
        ];
    }

    /**
     * Generate a verification report for a cart.
     *
     * @param array{
     *     cart_id: string,
     *     root_hash: string,
     *     item_hashes: array<string, string>,
     *     merkle_tree: array<string>,
     *     metadata: array<string, mixed>,
     *     timestamp: string,
     *     signature: string
     * }|null $storedProof
     * @return array{
     *     cart_id: string,
     *     verification_time: string,
     *     proof_verification: array<string, mixed>|null,
     *     integrity_check: array<string, mixed>|null,
     *     anchor_verification: array<string, mixed>|null,
     *     overall_status: string
     * }
     */
    public function generateVerificationReport(
        Cart $cart,
        ?array $storedProof = null,
        ?string $anchorId = null
    ): array {
        $report = [
            'cart_id' => $cart->getIdentifier(),
            'verification_time' => now()->toIso8601String(),
            'proof_verification' => null,
            'integrity_check' => null,
            'anchor_verification' => null,
            'overall_status' => 'unknown',
        ];

        if ($storedProof) {
            $report['proof_verification'] = $this->verifyProof($storedProof);
            $report['integrity_check'] = $this->verifyCartIntegrity($cart, $storedProof);
        }

        if ($anchorId && $storedProof) {
            $report['anchor_verification'] = $this->verifyAnchor(
                $anchorId,
                $storedProof['root_hash']
            );
        }

        $report['overall_status'] = $this->determineOverallStatus($report);

        return $report;
    }

    /**
     * Verify proof signature.
     *
     * @param array{
     *     root_hash: string,
     *     metadata: array<string, mixed>,
     *     signature: string
     * } $proof
     */
    private function verifySignature(array $proof): bool
    {
        $key = config('cart.blockchain.signing_key', config('app.key'));
        $data = $proof['root_hash'].json_encode($proof['metadata'], JSON_THROW_ON_ERROR);
        $expectedSignature = hash_hmac('sha256', $data, $key);

        return hash_equals($expectedSignature, $proof['signature']);
    }

    /**
     * Verify Merkle tree construction.
     *
     * @param  array<string, string>  $itemHashes
     * @param  array<string>  $merkleTree
     */
    private function verifyMerkleTree(
        array $itemHashes,
        array $merkleTree,
        string $rootHash
    ): bool {
        if (empty($itemHashes)) {
            return empty($merkleTree) || $merkleTree[0] === $rootHash;
        }

        $leaves = array_values($itemHashes);

        if (count($leaves) % 2 !== 0) {
            $leaves[] = $leaves[count($leaves) - 1];
        }

        $current = $leaves;

        while (count($current) > 1) {
            $next = [];

            for ($i = 0; $i < count($current); $i += 2) {
                $left = $current[$i];
                $right = $current[$i + 1] ?? $left;
                $next[] = hash('sha256', $left.$right);
            }

            $current = $next;
        }

        return $current[0] === $rootHash;
    }

    /**
     * Verify metadata structure.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function verifyMetadata(array $metadata): bool
    {
        $requiredFields = ['item_count', 'total_quantity', 'version'];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $metadata)) {
                return false;
            }
        }

        if ($metadata['item_count'] < 0 || $metadata['total_quantity'] < 0) {
            return false;
        }

        return true;
    }

    /**
     * Verify timestamp is valid.
     */
    private function verifyTimestamp(string $timestamp): bool
    {
        try {
            $proofTime = \Carbon\Carbon::parse($timestamp);

            return $proofTime->lte(now());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Find differences between stored and current item hashes.
     *
     * @param  array<string, string>  $storedHashes
     * @param  array<string, string>  $currentHashes
     * @return array<string>
     */
    private function findDifferences(array $storedHashes, array $currentHashes): array
    {
        $differences = [];

        foreach ($storedHashes as $itemId => $hash) {
            if (! isset($currentHashes[$itemId])) {
                $differences[] = "Item {$itemId} was removed";
            } elseif ($currentHashes[$itemId] !== $hash) {
                $differences[] = "Item {$itemId} was modified";
            }
        }

        foreach ($currentHashes as $itemId => $hash) {
            if (! isset($storedHashes[$itemId])) {
                $differences[] = "Item {$itemId} was added";
            }
        }

        return $differences;
    }

    /**
     * Determine overall verification status.
     *
     * @param  array<string, mixed>  $report
     */
    private function determineOverallStatus(array $report): string
    {
        if (! $report['proof_verification'] && ! $report['anchor_verification']) {
            return 'no_proof';
        }

        $proofValid = $report['proof_verification']['valid'] ?? false;
        $integrityValid = $report['integrity_check']['matches'] ?? false;
        $anchorValid = $report['anchor_verification']['anchored'] ?? null;

        if ($proofValid && $integrityValid) {
            if ($anchorValid === true) {
                return 'verified_anchored';
            }
            if ($anchorValid === false) {
                return 'verified_not_anchored';
            }

            return 'verified';
        }

        if ($proofValid && ! $integrityValid) {
            return 'tampered';
        }

        return 'invalid';
    }
}
