<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use Tests\Support\Cart\InMemoryStorage;

describe('Cart Metadata Management', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cart = new Cart($this->storage, 'test-user');
    });

    test('can set and retrieve metadata', function (): void {
        $this->cart->setMetadata('user_id', 123);
        $this->cart->setMetadata('currency', 'USD');
        $this->cart->setMetadata('notes', 'Special delivery instructions');

        expect($this->cart->getMetadata('user_id'))->toBe(123);
        expect($this->cart->getMetadata('currency'))->toBe('USD');
        expect($this->cart->getMetadata('notes'))->toBe('Special delivery instructions');
    });

    test('returns default value for missing metadata', function (): void {
        expect($this->cart->getMetadata('missing_key'))->toBeNull();
        expect($this->cart->getMetadata('missing_key', 'default_value'))->toBe('default_value');
        expect($this->cart->getMetadata('missing_key', 42))->toBe(42);
    });

    test('can check if metadata exists', function (): void {
        $this->cart->setMetadata('existing_key', 'value');

        expect($this->cart->hasMetadata('existing_key'))->toBeTrue();
        expect($this->cart->hasMetadata('missing_key'))->toBeFalse();
    });

    test('can remove metadata', function (): void {
        $this->cart->setMetadata('temp_key', 'temp_value');
        expect($this->cart->hasMetadata('temp_key'))->toBeTrue();

        $this->cart->removeMetadata('temp_key');
        expect($this->cart->hasMetadata('temp_key'))->toBeFalse();
        expect($this->cart->getMetadata('temp_key'))->toBeNull();
    });

    test('can set multiple metadata values at once', function (): void {
        $metadata = [
            'user_id' => 456,
            'session_id' => 'abc123',
            'preferences' => ['theme' => 'dark', 'language' => 'en'],
            'cart_created_at' => '2024-01-01 12:00:00',
        ];

        $this->cart->setMetadataBatch($metadata);

        expect($this->cart->getMetadata('user_id'))->toBe(456);
        expect($this->cart->getMetadata('session_id'))->toBe('abc123');
        expect($this->cart->getMetadata('preferences'))->toBe(['theme' => 'dark', 'language' => 'en']);
        expect($this->cart->getMetadata('cart_created_at'))->toBe('2024-01-01 12:00:00');
    });

    test('setMetadata returns cart instance for method chaining', function (): void {
        $result = $this->cart->setMetadata('key1', 'value1');
        expect($result)->toBe($this->cart);

        // Test method chaining
        $this->cart
            ->setMetadata('key2', 'value2')
            ->setMetadata('key3', 'value3');

        expect($this->cart->getMetadata('key2'))->toBe('value2');
        expect($this->cart->getMetadata('key3'))->toBe('value3');
    });

    test('removeMetadata returns cart instance for method chaining', function (): void {
        $this->cart->setMetadata('temp_key', 'temp_value');
        $result = $this->cart->removeMetadata('temp_key');

        expect($result)->toBe($this->cart);
    });

    test('setMetadataBatch returns cart instance for method chaining', function (): void {
        $result = $this->cart->setMetadataBatch(['key' => 'value']);
        expect($result)->toBe($this->cart);
    });

    test('setMetadataBatch returns early for empty array', function (): void {
        $result = $this->cart->setMetadataBatch([]);
        expect($result)->toBe($this->cart);
    });

    test('clearMetadata removes all metadata', function (): void {
        $this->cart->setMetadata('key1', 'value1');
        $this->cart->setMetadata('key2', 'value2');

        expect($this->cart->getMetadata('key1'))->toBe('value1');
        expect($this->cart->getMetadata('key2'))->toBe('value2');

        $result = $this->cart->clearMetadata();
        expect($result)->toBe($this->cart);

        expect($this->cart->getMetadata('key1'))->toBeNull();
        expect($this->cart->getMetadata('key2'))->toBeNull();
    });

    test('metadata persists across cart operations but not clear', function (): void {
        // Set metadata
        $this->cart->setMetadata('persistent_key', 'persistent_value');

        // Add items to cart
        $this->cart->add('item1', 'Product 1', 10.00, 1);
        $this->cart->add('item2', 'Product 2', 20.00, 2);

        // Update quantities
        $this->cart->update('item1', ['quantity' => 3]);

        // Remove an item
        $this->cart->remove('item2');

        // Metadata should persist through these operations
        expect($this->cart->getMetadata('persistent_key'))->toBe('persistent_value');

        // Clear cart - this removes everything including metadata
        $this->cart->clear();

        // After clear, metadata should be gone
        expect($this->cart->getMetadata('persistent_key'))->toBeNull();
    });

    test('can store different data types as metadata', function (): void {
        $this->cart->setMetadata('string', 'text');
        $this->cart->setMetadata('integer', 42);
        $this->cart->setMetadata('float', 3.14);
        $this->cart->setMetadata('boolean_true', true);
        $this->cart->setMetadata('boolean_false', false);
        $this->cart->setMetadata('array', ['a', 'b', 'c']);
        $this->cart->setMetadata('object', (object) ['prop' => 'value']);

        expect($this->cart->getMetadata('string'))->toBe('text');
        expect($this->cart->getMetadata('integer'))->toBe(42);
        expect($this->cart->getMetadata('float'))->toBe(3.14);
        expect($this->cart->getMetadata('boolean_true'))->toBeTrue();
        expect($this->cart->getMetadata('boolean_false'))->toBeFalse();
        expect($this->cart->getMetadata('array'))->toBe(['a', 'b', 'c']);
        expect($this->cart->getMetadata('object'))->toEqual((object) ['prop' => 'value']);
    });

    test('metadata is isolated between cart instances', function (): void {
        $cart2 = new Cart($this->storage, 'test-user-2', null, 'second_instance');

        $this->cart->setMetadata('instance_key', 'first_instance');
        $cart2->setMetadata('instance_key', 'second_instance');

        expect($this->cart->getMetadata('instance_key'))->toBe('first_instance');
        expect($cart2->getMetadata('instance_key'))->toBe('second_instance');
    });

    test('overwriting metadata works correctly', function (): void {
        $this->cart->setMetadata('changeable_key', 'original_value');
        expect($this->cart->getMetadata('changeable_key'))->toBe('original_value');

        $this->cart->setMetadata('changeable_key', 'updated_value');
        expect($this->cart->getMetadata('changeable_key'))->toBe('updated_value');
    });

    test('does not interfere with existing last_added_item_id functionality', function (): void {
        // Add an item which should set last_added_item_id
        $this->cart->add('item1', 'Product 1', 10.00, 1);

        // Set custom metadata
        $this->cart->setMetadata('custom_key', 'custom_value');

        // The last_added_item_id should still work
        expect($this->cart->getMetadata('custom_key'))->toBe('custom_value');

        // Add another item
        $this->cart->add('item2', 'Product 2', 20.00, 1);

        // Both should work independently
        expect($this->cart->getMetadata('custom_key'))->toBe('custom_value');
    });
});
