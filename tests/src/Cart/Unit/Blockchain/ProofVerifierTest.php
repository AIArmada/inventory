<?php

declare(strict_types=1);

use AIArmada\Cart\Blockchain\CartProofGenerator;
use AIArmada\Cart\Blockchain\ChainAnchor;
use AIArmada\Cart\Blockchain\ProofVerifier;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('cart.blockchain', [
        'enabled' => true,
        'provider' => 'internal',
        'signing_key' => 'test-signing-key',
    ]);
    Config::set('app.key', 'test-app-key');

    $this->storage = new InMemoryStorage;
    $this->proofGenerator = new CartProofGenerator;
    $this->chainAnchor = new ChainAnchor;
    $this->verifier = new ProofVerifier($this->proofGenerator, $this->chainAnchor);
});

describe('ProofVerifier', function (): void {
    it('can be instantiated', function (): void {
        expect($this->verifier)->toBeInstanceOf(ProofVerifier::class);
    });

    describe('proof verification', function (): void {
        it('verifies valid proof', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product A', 1000, 2);
            $cart->add('item-2', 'Product B', 500, 3);

            $proof = $this->proofGenerator->generateProof($cart);

            $result = $this->verifier->verifyProof($proof);

            expect($result['valid'])->toBeTrue()
                ->and($result['checks']['signature_valid'])->toBeTrue()
                ->and($result['checks']['metadata_valid'])->toBeTrue()
                ->and($result['checks']['timestamp_valid'])->toBeTrue()
                ->and($result['errors'])->toBeEmpty()
                ->and($result['verified_at'])->toBeString();
        });

        it('fails with invalid signature', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $proof = $this->proofGenerator->generateProof($cart);
            $proof['signature'] = 'invalid-signature';

            $result = $this->verifier->verifyProof($proof);

            expect($result['valid'])->toBeFalse()
                ->and($result['checks']['signature_valid'])->toBeFalse()
                ->and($result['errors'])->toContain('Proof signature is invalid');
        });

        it('fails with missing metadata fields', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $proof = $this->proofGenerator->generateProof($cart);
            $proof['metadata'] = ['item_count' => 1]; // Missing total_quantity and version

            $result = $this->verifier->verifyProof($proof);

            expect($result['checks']['metadata_valid'])->toBeFalse()
                ->and($result['errors'])->toContain('Metadata validation failed');
        });

        it('fails with negative metadata values', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $proof = $this->proofGenerator->generateProof($cart);
            $proof['metadata']['item_count'] = -1;

            $result = $this->verifier->verifyProof($proof);

            expect($result['checks']['metadata_valid'])->toBeFalse();
        });

        it('fails with future timestamp', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $proof = $this->proofGenerator->generateProof($cart);
            $proof['timestamp'] = now()->addHour()->toIso8601String();

            $result = $this->verifier->verifyProof($proof);

            expect($result['checks']['timestamp_valid'])->toBeFalse()
                ->and($result['errors'])->toContain('Timestamp is invalid or in the future');
        });

        it('fails with invalid timestamp format', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $proof = $this->proofGenerator->generateProof($cart);
            $proof['timestamp'] = 'invalid-date-format';

            $result = $this->verifier->verifyProof($proof);

            expect($result['checks']['timestamp_valid'])->toBeFalse();
        });
    });

    describe('item proof verification', function (): void {
        it('verifies item inclusion proof', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product A', 1000, 1);
            $cart->add('item-2', 'Product B', 2000, 1);

            $itemProof = $this->proofGenerator->generateItemProof($cart, 'item-1');

            $result = $this->verifier->verifyItemProof($itemProof);

            expect($result)->toBeTrue();
        });

        it('fails for tampered item proof', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $itemProof = $this->proofGenerator->generateItemProof($cart, 'item-1');
            $itemProof['item_hash'] = 'tampered-hash';

            $result = $this->verifier->verifyItemProof($itemProof);

            expect($result)->toBeFalse();
        });
    });

    describe('cart integrity verification', function (): void {
        it('verifies cart integrity when hashes match', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $storedProof = $this->proofGenerator->generateProof($cart);

            $result = $this->verifier->verifyCartIntegrity($cart, $storedProof);

            expect($result['matches'])->toBeTrue()
                ->and($result['current_hash'])->toBe($result['stored_hash'])
                ->and($result['differences'])->toBeEmpty();
        });

        it('detects cart tampering when items change', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $storedProof = $this->proofGenerator->generateProof($cart);

            // Modify cart after generating proof
            $cart->update('item-1', ['quantity' => 5]);

            $result = $this->verifier->verifyCartIntegrity($cart, $storedProof);

            expect($result['matches'])->toBeFalse()
                ->and($result['differences'])->toContain('Item item-1 was modified');
        });

        it('detects removed items', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product A', 1000, 1);
            $cart->add('item-2', 'Product B', 2000, 1);

            $storedProof = $this->proofGenerator->generateProof($cart);

            // Remove an item
            $cart->remove('item-1');

            $result = $this->verifier->verifyCartIntegrity($cart, $storedProof);

            expect($result['differences'])->toContain('Item item-1 was removed');
        });

        it('detects added items', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product A', 1000, 1);

            $storedProof = $this->proofGenerator->generateProof($cart);

            // Add new item
            $cart->add('item-2', 'Product B', 2000, 1);

            $result = $this->verifier->verifyCartIntegrity($cart, $storedProof);

            expect($result['differences'])->toContain('Item item-2 was added');
        });
    });

    describe('anchor verification', function (): void {
        it('verifies internal anchor', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $proof = $this->proofGenerator->generateProof($cart);
            $anchorResult = $this->chainAnchor->anchor($proof['root_hash']);

            $result = $this->verifier->verifyAnchor($anchorResult['anchor_id'], $proof['root_hash']);

            expect($result['anchored'])->toBeTrue()
                ->and($result['proof_valid'])->toBeTrue();
        });
    });

    describe('verification report', function (): void {
        it('generates complete report', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $storedProof = $this->proofGenerator->generateProof($cart);
            $anchorResult = $this->chainAnchor->anchor($storedProof['root_hash']);

            $report = $this->verifier->generateVerificationReport($cart, $storedProof, $anchorResult['anchor_id']);

            expect($report['cart_id'])->toBe('cart-123')
                ->and($report['proof_verification'])->not->toBeNull()
                ->and($report['integrity_check'])->not->toBeNull()
                ->and($report['anchor_verification'])->not->toBeNull()
                ->and($report['overall_status'])->toBe('verified_anchored');
        });

        it('returns no_proof status without stored proof', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $report = $this->verifier->generateVerificationReport($cart);

            expect($report['overall_status'])->toBe('no_proof')
                ->and($report['proof_verification'])->toBeNull()
                ->and($report['anchor_verification'])->toBeNull();
        });

        it('returns tampered status when integrity fails', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $storedProof = $this->proofGenerator->generateProof($cart);

            // Tamper with cart
            $cart->update('item-1', ['quantity' => 10]);

            $report = $this->verifier->generateVerificationReport($cart, $storedProof);

            expect($report['overall_status'])->toBe('tampered');
        });

        it('returns invalid status when proof verification fails', function (): void {
            $cart = new Cart($this->storage, 'cart-123');
            $cart->add('item-1', 'Product', 1000, 1);

            $storedProof = $this->proofGenerator->generateProof($cart);
            $storedProof['signature'] = 'invalid-signature';

            $report = $this->verifier->generateVerificationReport($cart, $storedProof);

            expect($report['overall_status'])->toBe('invalid');
        });
    });
});
