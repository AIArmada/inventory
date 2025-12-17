<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User as TestUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('Cart Model', function (): void {
    it('can be created with factory', function (): void {
        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
            'subtotal' => 1000,
            'total' => 1200,
            'currency' => 'USD',
        ]);

        expect($cart)->toBeInstanceOf(Cart::class);
        expect($cart->instance)->toBe('default');
    });

    it('returns correct table name', function (): void {
        $cart = new Cart();
        expect($cart->getTable())->toContain('snapshots');
    });

    it('formats money attributes correctly', function (): void {
        $cart = Cart::create([
            'identifier' => 'test-1',
            'instance' => 'default',
            'currency' => 'USD',
            'subtotal' => 1050, // $10.50
            'total' => 1200, // $12.00
            'savings' => 100, // $1.00
        ]);

        expect($cart->formattedSubtotal)->toBe('$10.50');
        expect($cart->formattedTotal)->toBe('$12.00');
        expect($cart->formattedSavings)->toBe('$1.00');
    });

    it('calculates dollar attributes', function (): void {
        $cart = Cart::create([
            'identifier' => 'test-2',
            'instance' => 'default',
            'subtotal' => 1050,
            'total' => 1200,
            'savings' => 100,
        ]);

        expect($cart->subtotalInDollars)->toBe(10.50);
        expect($cart->totalInDollars)->toBe(12.00);
        expect($cart->savingsInDollars)->toBe(1.00);
    });

    it('resolves cart instance', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $mockInstance = new \AIArmada\Cart\Cart(
            storage: $storage,
            identifier: 'session-123',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );
        $manager = Mockery::mock(CartInstanceManager::class);
        $manager->shouldReceive('resolve')
            ->with('default', 'session-123')
            ->andReturn($mockInstance);

        $this->app->instance(CartInstanceManager::class, $manager);

        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
        ]);

        $instance = $cart->getCartInstance();
        expect($instance)->toBe($mockInstance);
    });

    it('returns null and logs when cart instance cannot be resolved', function (): void {
        $manager = Mockery::mock(CartInstanceManager::class);
        $manager->shouldReceive('resolve')
            ->with('default', 'session-err')
            ->andThrow(new RuntimeException('nope'));

        $this->app->instance(CartInstanceManager::class, $manager);

        Log::shouldReceive('warning')->once();

        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-err',
        ]);

        expect($cart->getCartInstance())->toBeNull();
    });

    it('has relations', function (): void {
        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => 'item-1',
            'name' => 'Item 1',
            'price' => 100,
            'quantity' => 1,
        ]);

        $condition = CartCondition::create([
            'cart_id' => $cart->id,
            'name' => 'Promo',
            'type' => 'discount',
            'target' => 'cart.subtotal',
            'target_definition' => [],
            'value' => '10%',
        ]);

        expect($cart->items()->count())->toBe(1);
        expect($cart->cartConditions()->count())->toBe(1);
    });

    it('filters cart and item level conditions', function (): void {
        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-levels',
        ]);

        $cartLevel = CartCondition::create([
            'cart_id' => $cart->id,
            'name' => 'Cart Promo',
            'type' => 'discount',
            'target' => 'cart.subtotal',
            'target_definition' => [],
            'value' => '10%',
        ]);

        $itemLevel = CartCondition::create([
            'cart_id' => $cart->id,
            'item_id' => 'item-1',
            'name' => 'Item Promo',
            'type' => 'discount',
            'target' => 'item.price',
            'target_definition' => [],
            'value' => '5%',
        ]);

        expect($cart->cartLevelConditions()->pluck('id')->all())->toContain($cartLevel->id);
        expect($cart->cartLevelConditions()->pluck('id')->all())->not->toContain($itemLevel->id);

        expect($cart->itemLevelConditions()->pluck('id')->all())->toContain($itemLevel->id);
        expect($cart->itemLevelConditions()->pluck('id')->all())->not->toContain($cartLevel->id);
    });

    it('resolves current owner context and owner key', function (): void {
        config([
            'filament-cart.owner.enabled' => true,
            'filament-cart.owner.include_global' => false,
        ]);

        $user = TestUser::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $this->app->instance(OwnerResolverInterface::class, new class($user) implements OwnerResolverInterface {
            public function __construct(private TestUser $user) {}

            public function resolve(): ?\Illuminate\Database\Eloquent\Model
            {
                return $this->user;
            }
        });

        expect(Cart::resolveCurrentOwner()?->id)->toBe($user->id);
        expect(Cart::resolveOwnerKey($user))->toBe($user->getMorphClass() . ':' . $user->getKey());
        expect(Cart::makeOwnerKey($user->getMorphClass(), $user->getKey()))->toBe($user->getMorphClass() . ':' . $user->getKey());
    });

    it('resolves associated user relation', function (): void {
        $user = TestUser::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'secret',
        ]);

        $cart = Cart::create([
            'instance' => 'default',
            'identifier' => (string) $user->id,
        ]);

        expect($cart->user()->first()?->id)->toBe($user->id);
    });

    it('scopes query properly', function (): void {
        Cart::create([
            'instance' => 'default',
            'identifier' => 'abc',
        ]);
        Cart::create([
            'instance' => 'wishlist',
            'identifier' => 'def',
        ]);

        expect(Cart::instance('default')->count())->toBe(1);
        expect(Cart::instance('wishlist')->count())->toBe(1);
        expect(Cart::byIdentifier('abc')->count())->toBe(1);
    });

    it('detects statuses', function (): void {
        $active = Cart::create(['instance' => 'default', 'identifier' => 'active']);
        $abandoned = Cart::create([
            'instance' => 'default',
            'identifier' => 'abandoned',
            'checkout_started_at' => now()->subDay(),
            'checkout_abandoned_at' => now()->subHours(5),
            'recovered_at' => null,
        ]);
        $recovered = Cart::create([
            'instance' => 'default',
            'identifier' => 'recovered',
            'checkout_abandoned_at' => now()->subHours(5),
            'recovered_at' => now(),
        ]);
        $checkout = Cart::create([
            'instance' => 'default',
            'identifier' => 'checkout',
            'checkout_started_at' => now(),
            'checkout_abandoned_at' => null,
        ]);

        expect($active->isAbandoned())->toBeFalse();
        expect($abandoned->isAbandoned())->toBeTrue();
        expect($recovered->isAbandoned())->toBeFalse();
        expect($recovered->isRecovered())->toBeTrue();
        expect($checkout->isInCheckout())->toBeTrue();
    });

    it('detects fraud risk', function (): void {
        $high = Cart::create(['identifier' => 'h', 'instance' => 'default', 'fraud_risk_level' => 'high']);
        $medium = Cart::create(['identifier' => 'm', 'instance' => 'default', 'fraud_risk_level' => 'medium']);
        $low = Cart::create(['identifier' => 'l', 'instance' => 'default', 'fraud_risk_level' => 'low']);

        expect($high->hasFraudRisk())->toBeTrue();
        expect($medium->hasFraudRisk())->toBeTrue();
        expect($low->hasFraudRisk())->toBeFalse();

        expect($high->getFraudRiskColor())->toBe('danger');
        expect($medium->getFraudRiskColor())->toBe('warning');
        expect($low->getFraudRiskColor())->toBe('info');
    });

    it('checks if empty', function (): void {
        $empty = Cart::create(['identifier' => 'e', 'instance' => 'default', 'items_count' => 0]);
        $filled = Cart::create(['identifier' => 'f', 'instance' => 'default', 'items_count' => 5, 'quantity' => 5]);

        expect($empty->isEmpty())->toBeTrue();
        expect($filled->isEmpty())->toBeFalse();
    });
});
