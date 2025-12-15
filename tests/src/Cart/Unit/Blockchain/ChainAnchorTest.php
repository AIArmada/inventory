<?php

declare(strict_types=1);

use AIArmada\Cart\Blockchain\ChainAnchor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Config::set('cart.blockchain', [
        'enabled' => true,
        'provider' => 'internal',
        'batch_size' => 100,
        'anchor_interval' => 3600,
    ]);
});

describe('ChainAnchor', function (): void {
    it('can be instantiated', function (): void {
        $anchor = new ChainAnchor();

        expect($anchor)->toBeInstanceOf(ChainAnchor::class);
    });

    describe('internal anchoring', function (): void {
        it('anchors proof hash internally', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeTrue()
                ->and($result['anchor_id'])->toBeString()
                ->and($result['chain'])->toBe('internal')
                ->and($result['timestamp'])->toBeString()
                ->and($result['transaction_id'])->toBeNull()
                ->and($result['error'])->toBeNull();
        });

        it('verifies internal anchor', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result = $anchor->verify('some-anchor-id');

            expect($result['verified'])->toBeTrue()
                ->and($result['anchor_time'])->toBeString()
                ->and($result['block_number'])->toBeNull()
                ->and($result['confirmations'])->toBeNull();
        });
    });

    describe('ethereum anchoring', function (): void {
        it('fails when ethereum not configured', function (): void {
            Config::set('cart.blockchain.provider', 'ethereum');
            Config::set('cart.blockchain.ethereum_endpoint', null);
            Config::set('cart.blockchain.ethereum_contract', null);

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeFalse()
                ->and($result['chain'])->toBe('ethereum')
                ->and($result['error'])->toBe('Ethereum not configured');
        });

        it('anchors to ethereum when configured', function (): void {
            Config::set('cart.blockchain.provider', 'ethereum');
            Config::set('cart.blockchain.ethereum_endpoint', 'https://eth.example.com/rpc');
            Config::set('cart.blockchain.ethereum_contract', '0x1234567890abcdef');

            Http::fake([
                'https://eth.example.com/rpc' => Http::response([
                    'result' => '0xabc123transaction',
                ]),
            ]);

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeTrue()
                ->and($result['chain'])->toBe('ethereum')
                ->and($result['transaction_id'])->toBe('0xabc123transaction');
        });

        it('handles ethereum request failure', function (): void {
            Config::set('cart.blockchain.provider', 'ethereum');
            Config::set('cart.blockchain.ethereum_endpoint', 'https://eth.example.com/rpc');
            Config::set('cart.blockchain.ethereum_contract', '0x1234567890abcdef');

            Http::fake([
                'https://eth.example.com/rpc' => Http::response('Internal Server Error', 500),
            ]);

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeFalse()
                ->and($result['chain'])->toBe('ethereum')
                ->and($result['error'])->not->toBeNull();
        });

        it('handles ethereum request exception', function (): void {
            Config::set('cart.blockchain.provider', 'ethereum');
            Config::set('cart.blockchain.ethereum_endpoint', 'https://eth.example.com/rpc');
            Config::set('cart.blockchain.ethereum_contract', '0x1234567890abcdef');

            Http::fake(function (): void {
                throw new Exception('Network error');
            });

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeFalse()
                ->and($result['error'])->toBe('Network error');
        });

        it('verifies ethereum anchor returns false', function (): void {
            Config::set('cart.blockchain.provider', 'ethereum');

            $anchor = new ChainAnchor();
            $result = $anchor->verify('0xanchorid');

            expect($result['verified'])->toBeFalse()
                ->and($result['anchor_time'])->toBeNull();
        });
    });

    describe('bitcoin anchoring', function (): void {
        it('returns not implemented for bitcoin', function (): void {
            Config::set('cart.blockchain.provider', 'bitcoin');

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeFalse()
                ->and($result['chain'])->toBe('bitcoin')
                ->and($result['error'])->toBe('Bitcoin anchoring not yet implemented');
        });

        it('verifies bitcoin anchor returns false', function (): void {
            Config::set('cart.blockchain.provider', 'bitcoin');

            $anchor = new ChainAnchor();
            $result = $anchor->verify('bitcoin-anchor-id');

            expect($result['verified'])->toBeFalse();
        });
    });

    describe('opentimestamps anchoring', function (): void {
        it('anchors to opentimestamps successfully', function (): void {
            Config::set('cart.blockchain.provider', 'opentimestamps');

            Http::fake([
                'https://alice.btc.calendar.opentimestamps.org/digest' => Http::response('ots_proof_data'),
            ]);

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeTrue()
                ->and($result['chain'])->toBe('opentimestamps')
                ->and($result['anchor_id'])->toBe(base64_encode('ots_proof_data'));
        });

        it('handles opentimestamps request failure', function (): void {
            Config::set('cart.blockchain.provider', 'opentimestamps');

            Http::fake([
                'https://alice.btc.calendar.opentimestamps.org/digest' => Http::response('Error', 500),
            ]);

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeFalse()
                ->and($result['error'])->toBe('OpenTimestamps request failed');
        });

        it('handles opentimestamps exception', function (): void {
            Config::set('cart.blockchain.provider', 'opentimestamps');

            Http::fake(function (): void {
                throw new Exception('Connection timeout');
            });

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeFalse()
                ->and($result['error'])->toBe('Connection timeout');
        });

        it('verifies opentimestamps anchor returns false', function (): void {
            Config::set('cart.blockchain.provider', 'opentimestamps');

            $anchor = new ChainAnchor();
            $result = $anchor->verify('ots-anchor-id');

            expect($result['verified'])->toBeFalse();
        });
    });

    describe('batch anchoring', function (): void {
        it('anchors batch of proof hashes', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result = $anchor->anchorBatch(['hash1', 'hash2', 'hash3']);

            expect($result['success'])->toBeTrue()
                ->and($result['batch_root'])->toBeString()
                ->and($result['individual_proofs'])->toHaveCount(3)
                ->and($result['individual_proofs']['hash1']['position'])->toBe(0)
                ->and($result['individual_proofs']['hash2']['position'])->toBe(1)
                ->and($result['individual_proofs']['hash3']['position'])->toBe(2)
                ->and($result['anchor_result'])->toBeArray();
        });

        it('handles empty batch', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result = $anchor->anchorBatch([]);

            expect($result['success'])->toBeTrue()
                ->and($result['batch_root'])->toBeString()
                ->and($result['individual_proofs'])->toBeEmpty();
        });

        it('computes consistent batch root', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result1 = $anchor->anchorBatch(['a', 'b']);
            $result2 = $anchor->anchorBatch(['a', 'b']);

            expect($result1['batch_root'])->toBe($result2['batch_root']);
        });

        it('produces different roots for different batches', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result1 = $anchor->anchorBatch(['a', 'b']);
            $result2 = $anchor->anchorBatch(['c', 'd']);

            expect($result1['batch_root'])->not->toBe($result2['batch_root']);
        });
    });

    describe('status', function (): void {
        it('returns status metrics', function (): void {
            $anchor = new ChainAnchor();
            $status = $anchor->getStatus();

            expect($status)->toHaveKeys(['pending', 'anchored', 'failed', 'last_anchor_time'])
                ->and($status['pending'])->toBe(0)
                ->and($status['anchored'])->toBe(0)
                ->and($status['failed'])->toBe(0)
                ->and($status['last_anchor_time'])->toBeNull();
        });
    });

    describe('merkle tree computation', function (): void {
        it('computes merkle root for single hash', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result = $anchor->anchorBatch(['single_hash']);

            expect($result['batch_root'])->toBe('single_hash');
        });

        it('computes merkle root for two hashes', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result = $anchor->anchorBatch(['hash1', 'hash2']);

            $expectedRoot = hash('sha256', 'hash1' . 'hash2');
            expect($result['batch_root'])->toBe($expectedRoot);
        });

        it('computes merkle root for odd number of hashes', function (): void {
            Config::set('cart.blockchain.provider', 'internal');

            $anchor = new ChainAnchor();
            $result = $anchor->anchorBatch(['h1', 'h2', 'h3']);

            expect($result['batch_root'])->toBeString();
        });
    });

    describe('unknown provider', function (): void {
        it('falls back to internal for unknown provider', function (): void {
            Config::set('cart.blockchain.provider', 'unknown_blockchain');

            $anchor = new ChainAnchor();
            $result = $anchor->anchor('test-proof-hash');

            expect($result['success'])->toBeTrue()
                ->and($result['chain'])->toBe('internal');
        });

        it('falls back to internal for verify on unknown provider', function (): void {
            Config::set('cart.blockchain.provider', 'unknown_blockchain');

            $anchor = new ChainAnchor();
            $result = $anchor->verify('anchor-id');

            expect($result['verified'])->toBeTrue();
        });
    });
});
