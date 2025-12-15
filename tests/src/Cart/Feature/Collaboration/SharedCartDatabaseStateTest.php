<?php

declare(strict_types=1);

use AIArmada\Cart\Broadcasting\CartChannel;
use AIArmada\Cart\Collaboration\SharedCart;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

describe('SharedCart database-backed collaboration', function (): void {
    beforeEach(function (): void {
        Cart::clear();

        Schema::table('carts', function (Blueprint $table): void {
            $table->boolean('is_collaborative')->default(false);
            $table->string('owner_user_id')->nullable();
            $table->json('collaborators')->nullable();
            $table->integer('max_collaborators')->default(5);
            $table->string('collaboration_mode', 20)->default('edit');
            $table->string('share_token', 64)->nullable();
            $table->timestamp('share_expires_at')->nullable();
        });
    });

    it('persists collaboration state and authorizes channel join', function (): void {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'shared-cart-owner@example.com',
            'password' => 'secret',
        ]);

        $collaborator = User::query()->create([
            'name' => 'Collaborator',
            'email' => 'shared-cart-collaborator@example.com',
            'password' => 'secret',
        ]);

        Cart::add('item', 'Item', 10.00, 1);
        $cart = Cart::getCurrentCart();
        $cartId = $cart->getId();

        expect($cartId)->not->toBeNull();

        SharedCart::fromCart($cart)->enableCollaboration($owner);

        $row = DB::table('carts')->where('id', $cartId)->first();
        expect($row)->not->toBeNull();
        expect((bool) ($row->is_collaborative ?? false))->toBeTrue();
        expect((string) ($row->owner_user_id ?? ''))->toBe((string) $owner->getKey());

        $channel = app(CartChannel::class);

        $ownerJoin = $channel->join($owner, $cartId);
        expect($ownerJoin)->toBeArray()
            ->and($ownerJoin['role'])->toBe('owner');

        DB::table('carts')
            ->where('id', $cartId)
            ->update([
                'collaborators' => json_encode([[
                    'user_id' => (string) $collaborator->getKey(),
                    'email' => $collaborator->email,
                    'role' => 'editor',
                    'status' => 'active',
                ]], JSON_THROW_ON_ERROR),
            ]);

        $collaboratorJoin = $channel->join($collaborator, $cartId);
        expect($collaboratorJoin)->toBeArray()
            ->and($collaboratorJoin['role'])->toBe('editor');

        $stranger = User::query()->create([
            'name' => 'Stranger',
            'email' => 'shared-cart-stranger@example.com',
            'password' => 'secret',
        ]);

        expect($channel->join($stranger, $cartId))->toBeFalse();
    });
});
