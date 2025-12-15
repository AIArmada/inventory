<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Wishlist;
use AIArmada\Customers\Models\WishlistItem;

describe('Wishlist Model', function (): void {
    beforeEach(function (): void {
        $this->customer = Customer::create([
            'first_name' => 'Wishlist',
            'last_name' => 'Test',
            'email' => 'wishlist-' . uniqid() . '@example.com',
            'status' => CustomerStatus::Active,
        ]);
    });

    describe('Creation', function (): void {
        it('can create a wishlist', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'My Wishlist',
            ]);

            expect($wishlist)->toBeInstanceOf(Wishlist::class)
                ->and($wishlist->id)->not->toBeEmpty()
                ->and($wishlist->name)->toBe('My Wishlist');
        });

        it('generates share token on creation', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Token Test',
            ]);

            expect($wishlist->share_token)->not->toBeEmpty()
                ->and(mb_strlen($wishlist->share_token))->toBeGreaterThan(10);
        });

        it('defaults to private', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Private Test',
            ]);

            expect($wishlist->is_public)->toBeFalse();
        });

        it('defaults to not default', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Default Test',
            ]);

            expect($wishlist->is_default)->toBeFalse();
        });
    });

    describe('Relationships', function (): void {
        it('belongs to a customer', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Relationship Test',
            ]);

            expect($wishlist->customer)->toBeInstanceOf(Customer::class)
                ->and($wishlist->customer->id)->toBe($this->customer->id);
        });

        it('has many items', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Items Test',
            ]);

            expect($wishlist->items())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        });
    });

    describe('Sharing Helpers', function (): void {
        it('returns share url with token', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Share URL Test',
            ]);

            $url = $wishlist->getShareUrl();

            expect($url)->toContain('wishlist/shared')
                ->and($url)->toContain($wishlist->share_token);
        });

        it('can make wishlist public', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Make Public Test',
                'is_public' => false,
            ]);

            $wishlist->makePublic();

            expect($wishlist->fresh()->is_public)->toBeTrue();
        });

        it('can make wishlist private', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Make Private Test',
                'is_public' => true,
            ]);

            $wishlist->makePrivate();

            expect($wishlist->fresh()->is_public)->toBeFalse();
        });

        it('can regenerate share token', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Regenerate Token Test',
            ]);

            $oldToken = $wishlist->share_token;
            $wishlist->regenerateShareToken();

            expect($wishlist->fresh()->share_token)->not->toBe($oldToken);
        });
    });

    describe('Default Management', function (): void {
        it('can set as default wishlist', function (): void {
            $wishlist1 = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'First Wishlist',
                'is_default' => true,
            ]);

            $wishlist2 = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Second Wishlist',
            ]);

            $wishlist2->setAsDefault();

            expect($wishlist2->fresh()->is_default)->toBeTrue()
                ->and($wishlist1->fresh()->is_default)->toBeFalse();
        });
    });

    describe('Scopes', function (): void {
        it('can filter public wishlists', function (): void {
            Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Public',
                'is_public' => true,
            ]);

            Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Private',
                'is_public' => false,
            ]);

            $public = Wishlist::public()->get();

            expect($public->every(fn ($w) => $w->is_public))->toBeTrue();
        });

        it('can filter default wishlists', function (): void {
            Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Default',
                'is_default' => true,
            ]);

            $default = Wishlist::default()->get();

            expect($default->every(fn ($w) => $w->is_default))->toBeTrue();
        });
    });

    describe('Item Management', function (): void {
        it('can add a product to wishlist', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Add Product Test',
            ]);

            $item = $wishlist->addProduct('App\\Models\\Product', 'product-123');

            expect($item)->toBeInstanceOf(WishlistItem::class)
                ->and($item->product_type)->toBe('App\\Models\\Product')
                ->and($item->product_id)->toBe('product-123');
        });

        it('returns existing item when adding duplicate product', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Duplicate Test',
            ]);

            $item1 = $wishlist->addProduct('App\\Models\\Product', 'product-dup');
            $item2 = $wishlist->addProduct('App\\Models\\Product', 'product-dup');

            expect($item1->id)->toBe($item2->id)
                ->and($wishlist->items()->count())->toBe(1);
        });

        it('can remove product from wishlist', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Remove Product Test',
            ]);

            $wishlist->addProduct('App\\Models\\Product', 'product-remove');

            $result = $wishlist->removeProduct('App\\Models\\Product', 'product-remove');

            expect($result)->toBeTrue()
                ->and($wishlist->items()->count())->toBe(0);
        });

        it('returns false when removing non-existent product', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Non-existent Remove',
            ]);

            $result = $wishlist->removeProduct('App\\Models\\Product', 'does-not-exist');

            expect($result)->toBeFalse();
        });

        it('can check if has product', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Has Product Test',
            ]);

            $wishlist->addProduct('App\\Models\\Product', 'product-has');

            expect($wishlist->hasProduct('App\\Models\\Product', 'product-has'))->toBeTrue()
                ->and($wishlist->hasProduct('App\\Models\\Product', 'product-not'))->toBeFalse();
        });

        it('can clear all items', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Clear Items Test',
            ]);

            $wishlist->addProduct('App\\Models\\Product', 'product-1');
            $wishlist->addProduct('App\\Models\\Product', 'product-2');

            expect($wishlist->items()->count())->toBe(2);

            $wishlist->clear();

            expect($wishlist->items()->count())->toBe(0);
        });
    });

    describe('Cascade Deletion', function (): void {
        it('deletes items when wishlist is deleted', function (): void {
            $wishlist = Wishlist::create([
                'customer_id' => $this->customer->id,
                'name' => 'Cascade Test',
            ]);

            $item = WishlistItem::create([
                'wishlist_id' => $wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-cascade',
            ]);

            $itemId = $item->id;
            $wishlist->delete();

            expect(WishlistItem::find($itemId))->toBeNull();
        });
    });
});

describe('WishlistItem Model', function (): void {
    beforeEach(function (): void {
        $this->customer = Customer::create([
            'first_name' => 'Item',
            'last_name' => 'Test',
            'email' => 'item-' . uniqid() . '@example.com',
            'status' => CustomerStatus::Active,
        ]);

        $this->wishlist = Wishlist::create([
            'customer_id' => $this->customer->id,
            'name' => 'Item Test Wishlist',
        ]);
    });

    describe('Creation', function (): void {
        it('can create an item', function (): void {
            $item = WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-123',
            ]);

            expect($item)->toBeInstanceOf(WishlistItem::class)
                ->and($item->id)->not->toBeEmpty();
        });

        it('sets added_at on creation', function (): void {
            $item = WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-added-at',
            ]);

            expect($item->added_at)->not->toBeNull();
        });

        it('defaults notification flags to false', function (): void {
            $item = WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-notif',
            ]);

            expect($item->notified_on_sale)->toBeFalse()
                ->and($item->notified_in_stock)->toBeFalse();
        });
    });

    describe('Relationships', function (): void {
        it('belongs to a wishlist', function (): void {
            $item = WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-rel',
            ]);

            expect($item->wishlist)->toBeInstanceOf(Wishlist::class)
                ->and($item->wishlist->id)->toBe($this->wishlist->id);
        });
    });

    describe('Notification Helpers', function (): void {
        it('can mark sale notified', function (): void {
            $item = WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-sale',
            ]);

            $item->markSaleNotified();

            expect($item->fresh()->notified_on_sale)->toBeTrue();
        });

        it('can mark stock notified', function (): void {
            $item = WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-stock',
            ]);

            $item->markStockNotified();

            expect($item->fresh()->notified_in_stock)->toBeTrue();
        });

        it('can reset notifications', function (): void {
            $item = WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-reset',
                'notified_on_sale' => true,
                'notified_in_stock' => true,
            ]);

            $item->resetNotifications();

            expect($item->fresh()->notified_on_sale)->toBeFalse()
                ->and($item->fresh()->notified_in_stock)->toBeFalse();
        });
    });

    describe('Scopes', function (): void {
        it('can filter needs stock notification', function (): void {
            WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-needs-stock',
                'notified_in_stock' => false,
            ]);

            WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-notified',
                'notified_in_stock' => true,
            ]);

            $needsStock = WishlistItem::needsStockNotification()->get();

            expect($needsStock->every(fn ($i) => ! $i->notified_in_stock))->toBeTrue();
        });

        it('can filter needs sale notification', function (): void {
            WishlistItem::create([
                'wishlist_id' => $this->wishlist->id,
                'product_type' => 'App\\Models\\Product',
                'product_id' => 'product-needs-sale',
                'notified_on_sale' => false,
            ]);

            $needsSale = WishlistItem::needsSaleNotification()->get();

            expect($needsSale->every(fn ($i) => ! $i->notified_on_sale))->toBeTrue();
        });
    });
});
