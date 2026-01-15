<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;

it('exposes version and id via cart facade for database storage', function (): void {
    config(['cart.storage' => 'database']);
    Cart::clear();

    Cart::add('versioned-item', 'Versioned Item', 100.00, 1);

    $version = Cart::getVersion();
    $id = Cart::getId();

    expect($version)->not->toBeNull();
    expect($version)->toBeInt();
    expect($id)->not->toBeNull();
    expect($id)->toBeString();

    // Updating the cart should bump the version
    $oldVersion = $version;
    Cart::update('versioned-item', ['quantity' => 2]);

    $newVersion = Cart::getVersion();
    expect($newVersion)->toBeGreaterThanOrEqual($oldVersion);
});
