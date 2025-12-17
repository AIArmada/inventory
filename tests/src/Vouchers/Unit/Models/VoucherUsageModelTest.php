<?php

declare(strict_types=1);

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

describe('VoucherUsage Model', function (): void {
    describe('class structure', function (): void {
        it('is a final class', function (): void {
            $reflection = new ReflectionClass(VoucherUsage::class);
            expect($reflection->isFinal())->toBeTrue();
        });

        it('uses HasUuids trait', function (): void {
            $traits = class_uses_recursive(VoucherUsage::class);
            expect($traits)->toContain(HasUuids::class);
        });

        it('extends Eloquent Model', function (): void {
            $usage = new VoucherUsage;
            expect($usage)->toBeInstanceOf(Model::class);
        });

        it('has timestamps disabled', function (): void {
            $usage = new VoucherUsage;
            expect($usage->timestamps)->toBeFalse();
        });
    });

    describe('channel constants', function (): void {
        it('defines CHANNEL_AUTOMATIC constant', function (): void {
            expect(VoucherUsage::CHANNEL_AUTOMATIC)->toBe('automatic');
        });

        it('defines CHANNEL_MANUAL constant', function (): void {
            expect(VoucherUsage::CHANNEL_MANUAL)->toBe('manual');
        });

        it('defines CHANNEL_API constant', function (): void {
            expect(VoucherUsage::CHANNEL_API)->toBe('api');
        });

        it('all channel constants are strings', function (): void {
            expect(VoucherUsage::CHANNEL_AUTOMATIC)->toBeString()
                ->and(VoucherUsage::CHANNEL_MANUAL)->toBeString()
                ->and(VoucherUsage::CHANNEL_API)->toBeString();
        });
    });

    describe('fillable attributes', function (): void {
        it('has correct fillable fields', function (): void {
            $usage = new VoucherUsage;
            $expected = [
                'voucher_id',
                'discount_amount',
                'currency',
                'channel',
                'notes',
                'metadata',
                'redeemed_by_type',
                'redeemed_by_id',
                'used_at',
                'target_definition',
            ];

            expect($usage->getFillable())->toBe($expected);
        });

        it('can be mass assigned', function (): void {
            $usage = new VoucherUsage([
                'voucher_id' => 'voucher-123',
                'discount_amount' => 1500,
                'currency' => 'MYR',
                'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
                'notes' => 'Applied at checkout',
                'metadata' => ['order_id' => 'order-456'],
                'redeemed_by_type' => 'App\\Models\\User',
                'redeemed_by_id' => 'user-789',
                'used_at' => '2025-01-15 10:30:00',
                'target_definition' => ['products' => ['sku-1', 'sku-2']],
            ]);

            expect($usage->voucher_id)->toBe('voucher-123')
                ->and($usage->discount_amount)->toBe(1500)
                ->and($usage->currency)->toBe('MYR')
                ->and($usage->channel)->toBe(VoucherUsage::CHANNEL_AUTOMATIC)
                ->and($usage->notes)->toBe('Applied at checkout')
                ->and($usage->metadata)->toBe(['order_id' => 'order-456'])
                ->and($usage->redeemed_by_type)->toBe('App\\Models\\User')
                ->and($usage->redeemed_by_id)->toBe('user-789')
                ->and($usage->target_definition)->toBe(['products' => ['sku-1', 'sku-2']]);
        });

        it('allows nullable fields to be null', function (): void {
            $usage = new VoucherUsage([
                'voucher_id' => 'voucher-123',
                'discount_amount' => 1000,
                'currency' => 'MYR',
                'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
                'used_at' => now(),
            ]);

            expect($usage->notes)->toBeNull()
                ->and($usage->metadata)->toBeNull()
                ->and($usage->redeemed_by_type)->toBeNull()
                ->and($usage->redeemed_by_id)->toBeNull()
                ->and($usage->target_definition)->toBeNull();
        });
    });

    describe('relationships', function (): void {
        it('defines voucher relationship as BelongsTo', function (): void {
            $usage = new VoucherUsage;
            $relation = $usage->voucher();

            expect($relation)->toBeInstanceOf(BelongsTo::class)
                ->and($relation->getRelated())->toBeInstanceOf(Voucher::class);
        });

        it('defines redeemedBy relationship as MorphTo', function (): void {
            $usage = new VoucherUsage;
            $relation = $usage->redeemedBy();

            expect($relation)->toBeInstanceOf(MorphTo::class);
        });
    });

    describe('isManual method', function (): void {
        it('returns true for manual channel', function (): void {
            $usage = new VoucherUsage;
            $usage->channel = VoucherUsage::CHANNEL_MANUAL;

            expect($usage->isManual())->toBeTrue();
        });

        it('returns false for automatic channel', function (): void {
            $usage = new VoucherUsage;
            $usage->channel = VoucherUsage::CHANNEL_AUTOMATIC;

            expect($usage->isManual())->toBeFalse();
        });

        it('returns false for api channel', function (): void {
            $usage = new VoucherUsage;
            $usage->channel = VoucherUsage::CHANNEL_API;

            expect($usage->isManual())->toBeFalse();
        });
    });

    describe('casts', function (): void {
        it('has correct cast definitions', function (): void {
            $usage = new VoucherUsage;
            $casts = $usage->getCasts();

            expect($casts['discount_amount'])->toBe('integer')
                ->and($casts['metadata'])->toBe('array')
                ->and($casts['target_definition'])->toBe('array')
                ->and($casts['used_at'])->toBe('datetime');
        });

        it('casts discount_amount to integer', function (): void {
            $usage = new VoucherUsage;
            $usage->discount_amount = '2500';

            expect($usage->discount_amount)->toBeInt()
                ->and($usage->discount_amount)->toBe(2500);
        });

        it('casts metadata to array', function (): void {
            $usage = new VoucherUsage;
            $usage->metadata = ['transaction_id' => 'txn-001', 'applied_rules' => ['rule1']];

            expect($usage->metadata)->toBeArray()
                ->and($usage->metadata['transaction_id'])->toBe('txn-001');
        });

        it('casts target_definition to array', function (): void {
            $usage = new VoucherUsage;
            $usage->target_definition = ['categories' => ['electronics'], 'min_quantity' => 2];

            expect($usage->target_definition)->toBeArray()
                ->and($usage->target_definition['categories'])->toBe(['electronics']);
        });

        it('casts used_at to datetime', function (): void {
            $usage = new VoucherUsage;
            $usage->used_at = '2025-01-15 14:30:00';

            expect($usage->used_at)->toBeInstanceOf(Carbon::class)
                ->and($usage->used_at->format('Y-m-d'))->toBe('2025-01-15');
        });
    });

    describe('userIdentifier attribute', function (): void {
        it('defines userIdentifier as protected method', function (): void {
            $reflection = new ReflectionClass(VoucherUsage::class);
            $method = $reflection->getMethod('userIdentifier');

            expect($method->isProtected())->toBeTrue();
        });

        it('returns Attribute instance', function (): void {
            $usage = new VoucherUsage;
            $reflection = new ReflectionClass(VoucherUsage::class);
            $method = $reflection->getMethod('userIdentifier');
            $method->setAccessible(true);

            $result = $method->invoke($usage);

            expect($result)->toBeInstanceOf(Attribute::class);
        });

        it('returns N/A when no redeemedBy relation', function (): void {
            $usage = new VoucherUsage;
            $usage->redeemed_by_type = null;
            $usage->redeemed_by_id = null;

            expect($usage->user_identifier)->toBe('N/A');
        });

        it('has userIdentifier accessor that accesses redeemedBy', function (): void {
            // We test the accessor method exists and returns Attribute - the full flow
            // requires database integration which is covered elsewhere
            $usage = new VoucherUsage;

            $reflection = new ReflectionClass($usage);
            $method = $reflection->getMethod('userIdentifier');
            $method->setAccessible(true);

            $attribute = $method->invoke($usage);

            expect($attribute)->toBeInstanceOf(Attribute::class);
            // Verify it's a get-only accessor
            $getProperty = (new ReflectionClass($attribute))->getProperty('get');
            $getProperty->setAccessible(true);

            expect($getProperty->getValue($attribute))->toBeInstanceOf(Closure::class);
        });
    });

    describe('getTable method', function (): void {
        it('returns table name from config', function (): void {
            config(['vouchers.database.tables.voucher_usage' => 'custom_voucher_usages']);

            $usage = new VoucherUsage;

            expect($usage->getTable())->toBe('custom_voucher_usages');
        });

        it('returns another custom table name', function (): void {
            config(['vouchers.database.tables.voucher_usage' => 'my_usage_logs']);

            $usage = new VoucherUsage;

            expect($usage->getTable())->toBe('my_usage_logs');
        });

        it('returns prefixed table name from config', function (): void {
            config(['vouchers.database.tables.voucher_usage' => 'acme_voucher_usage']);

            $usage = new VoucherUsage;

            expect($usage->getTable())->toBe('acme_voucher_usage');
        });
    });
});
