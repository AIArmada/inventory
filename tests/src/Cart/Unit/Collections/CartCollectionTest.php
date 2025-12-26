<?php

declare(strict_types=1);

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Models\CartItem;

describe('CartCollection Basic Operations', function (): void {
    it('creates empty collection', function (): void {
        $collection = new CartCollection;

        expect($collection)->toBeInstanceOf(CartCollection::class);
        expect($collection->isEmpty())->toBeTrue();
        expect($collection->count())->toBe(0);
    });

    it('creates collection from items', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 20.00, 2),
        ];

        $collection = new CartCollection($items);

        expect($collection->count())->toBe(2);
        expect($collection->isEmpty())->toBeFalse();
    });

    it('adds items to collection', function (): void {
        $collection = new CartCollection;
        $item = new CartItem('item', 'Item', 15.00, 1);

        $collection->push($item);

        expect($collection->count())->toBe(1);
        expect($collection->first())->toBe($item);
    });

    it('adds items using addItem method', function (): void {
        $collection = new CartCollection;
        $item = new CartItem('item-1', 'Item 1', 10.00, 1);

        $result = $collection->addItem($item);

        expect($result)->toBeInstanceOf(CartCollection::class);
        expect($collection->count())->toBe(1);
        expect($collection->has('item-1'))->toBeTrue();
    });

    it('removes items from collection', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection([$item1, $item2]);
        $filtered = $collection->filter(fn ($item) => $item->id !== 'item-1');

        expect($filtered->count())->toBe(1);
        expect($filtered->first()->id)->toBe('item-2');
    });

    it('removes items using removeItem method', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $result = $collection->removeItem('item-1');

        expect($result)->toBeInstanceOf(CartCollection::class);
        expect($collection->count())->toBe(1);
        expect($collection->has('item-1'))->toBeFalse();
        expect($collection->has('item-2'))->toBeTrue();
    });

    it('gets item by ID using getItem method', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $retrieved = $collection->getItem('item-1');

        expect($retrieved)->not->toBeNull();
        expect($retrieved->id)->toBe('item-1');
        expect($retrieved->name)->toBe('Item 1');
    });

    it('returns null when getting non-existent item', function (): void {
        $collection = new CartCollection;

        $retrieved = $collection->getItem('non-existent');

        expect($retrieved)->toBeNull();
    });

    it('checks if item exists using hasItem method', function (): void {
        $item = new CartItem('item-1', 'Item 1', 10.00, 1);
        $collection = new CartCollection;
        $collection->addItem($item);

        expect($collection->hasItem('item-1'))->toBeTrue();
        expect($collection->hasItem('non-existent'))->toBeFalse();
    });

    it('gets total quantity using getTotalQuantity method', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 2);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 3);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 5);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $totalQty = $collection->getTotalQuantity();

        expect($totalQty)->toBe(10);
    });
});

describe('CartCollection Calculations', function (): void {
    it('calculates subtotal', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 2), // 20.00
            new CartItem('item-2', 'Item 2', 15.00, 3), // 45.00
        ];

        $collection = new CartCollection($items);
        $subtotal = $collection->sum(fn ($item) => $item->price * $item->quantity);

        expect($subtotal)->toBe(6500);
    });

    it('calculates total quantity', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 2),
            new CartItem('item-2', 'Item 2', 20.00, 3),
            new CartItem('item-3', 'Item 3', 30.00, 1),
        ];

        $collection = new CartCollection($items);
        $totalQty = $collection->sum('quantity');

        expect($totalQty)->toBe(6);
    });

    it('filters items by criteria', function (): void {
        $items = [
            new CartItem('cheap', 'Cheap', 5.00, 1),
            new CartItem('expensive', 'Expensive', 100.00, 1),
            new CartItem('moderate', 'Moderate', 25.00, 1),
        ];

        $collection = new CartCollection($items);
        $expensive = $collection->filter(fn ($item) => $item->price > 2000);

        expect($expensive->count())->toBe(2);
    });

    it('sorts items by price', function (): void {
        $items = [
            new CartItem('mid', 'Mid', 50.00, 1),
            new CartItem('low', 'Low', 10.00, 1),
            new CartItem('high', 'High', 100.00, 1),
        ];

        $collection = new CartCollection($items);
        $sorted = $collection->sortBy(fn ($item) => $item->price);

        expect($sorted->first()->id)->toBe('low');
        expect($sorted->last()->id)->toBe('high');
    });
});

describe('CartCollection Search and Find', function (): void {
    it('finds item by ID', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 20.00, 1),
            new CartItem('item-3', 'Item 3', 30.00, 1),
        ];

        $collection = new CartCollection($items);
        $found = $collection->firstWhere('id', 'item-2');

        expect($found)->not->toBeNull();
        expect($found->name)->toBe('Item 2');
    });

    it('finds items by attribute', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, ['category' => 'electronics']);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, ['category' => 'books']);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, ['category' => 'electronics']);

        $collection = new CartCollection([$item1, $item2, $item3]);
        $electronics = $collection->filter(fn ($item) => $item->getAttribute('category') === 'electronics');

        expect($electronics->count())->toBe(2);
    });

    it('checks if collection contains item', function (): void {
        $item = new CartItem('item', 'Item', 10.00, 1);
        $collection = new CartCollection([$item]);

        expect($collection->contains($item))->toBeTrue();
        expect($collection->contains('id', 'item'))->toBeTrue();
        expect($collection->contains('id', 'nonexistent'))->toBeFalse();
    });

    it('plucks specific values', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 20.00, 1),
            new CartItem('item-3', 'Item 3', 30.00, 1),
        ];

        $collection = new CartCollection($items);
        $names = $collection->pluck('name');

        expect($names->toArray())->toBe(['Item 1', 'Item 2', 'Item 3']);
    });
});

describe('CartCollection Transformations', function (): void {
    it('maps items to new values', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 2),
            new CartItem('item-2', 'Item 2', 20.00, 1),
        ];

        $collection = new CartCollection($items);
        $totals = $collection->map(fn ($item) => $item->price * $item->quantity);

        expect($totals->toArray())->toBe([2000, 2000]);  // 1000*2, 2000*1 in cents
    });

    it('groups items by attribute', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, ['type' => 'physical']);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, ['type' => 'digital']);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, ['type' => 'physical']);

        $collection = new CartCollection([$item1, $item2, $item3]);
        $grouped = $collection->groupBy(fn ($item) => $item->getAttribute('type'));

        expect($grouped->has('physical'))->toBeTrue();
        expect($grouped->has('digital'))->toBeTrue();
        expect($grouped->get('physical')->count())->toBe(2);
    });

    it('chunks collection into smaller collections', function (): void {
        $items = collect(range(1, 10))->map(fn ($i) => new CartItem("item-{$i}", "Item {$i}", 10.00, 1));

        $collection = new CartCollection($items->toArray());
        $chunks = $collection->chunk(3);

        expect($chunks->count())->toBe(4);
        expect($chunks->first()->count())->toBe(3);
    });

    it('takes subset of items', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 20.00, 1),
            new CartItem('item-3', 'Item 3', 30.00, 1),
            new CartItem('item-4', 'Item 4', 40.00, 1),
            new CartItem('item-5', 'Item 5', 50.00, 1),
        ];

        $collection = new CartCollection($items);
        $subset = $collection->take(3);

        expect($subset->count())->toBe(3);
        expect($subset->first()->id)->toBe('item-1');
    });
});

describe('CartCollection Aggregations', function (): void {
    it('calculates minimum price', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 50.00, 1),
            new CartItem('item-3', 'Item 3', 25.00, 1),
        ];

        $collection = new CartCollection($items);
        $min = $collection->min(fn ($item) => $item->price);

        expect($min)->toBe(1000);
    });

    it('calculates maximum price', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 50.00, 1),
            new CartItem('item-3', 'Item 3', 25.00, 1),
        ];

        $collection = new CartCollection($items);
        $max = $collection->max(fn ($item) => $item->price);

        expect($max)->toBe(5000);
    });

    it('calculates average price', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 20.00, 1),
            new CartItem('item-3', 'Item 3', 30.00, 1),
        ];

        $collection = new CartCollection($items);
        $avg = $collection->avg(fn ($item) => $item->price);

        expect($avg)->toBe(2000);
    });

    it('reduces collection to single value', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 2),
            new CartItem('item-2', 'Item 2', 15.00, 3),
        ];

        $collection = new CartCollection($items);
        $total = $collection->reduce(
            fn ($carry, $item) => $carry + ($item->price * $item->quantity),
            0
        );

        expect($total)->toBe(6500);
    });
});

describe('CartCollection Edge Cases', function (): void {
    it('handles empty collection operations', function (): void {
        $collection = new CartCollection;

        expect($collection->sum('quantity'))->toBe(0);
        expect($collection->first())->toBeNull();
        expect($collection->last())->toBeNull();
    });

    it('handles single item collection', function (): void {
        $item = new CartItem('single', 'Single', 10.00, 1);
        $collection = new CartCollection([$item]);

        expect($collection->first())->toBe($item);
        expect($collection->last())->toBe($item);
    });

    it('chains multiple operations', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 50.00, 2),
            new CartItem('item-3', 'Item 3', 25.00, 1),
            new CartItem('item-4', 'Item 4', 5.00, 3),
        ];

        $collection = new CartCollection($items);

        $result = $collection
            ->filter(fn ($item) => $item->price > 500)  // > 5.00 in cents
            ->sortBy(fn ($item) => $item->price)
            ->take(2);

        expect($result->count())->toBe(2);
        expect($result->first()->id)->toBe('item-1');
    });

    it('converts to array', function (): void {
        $items = [
            new CartItem('item-1', 'Item 1', 10.00, 1),
            new CartItem('item-2', 'Item 2', 20.00, 1),
        ];

        $collection = new CartCollection($items);
        $array = $collection->toArray();

        expect($array)->toBeArray();
        expect(count($array))->toBe(2);
    });
});

describe('CartCollection Totals and Subtotals', function (): void {
    it('calculates subtotal', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 2);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 3);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $subtotal = $collection->subtotal();

        expect($subtotal)->toBe(8000); // (10*2) + (20*3)
    });

    it('calculates total', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 15.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 25.00, 2);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $total = $collection->total();

        expect($total)->toBe(6500); // 15 + (25*2)
    });

    it('calculates subtotal without conditions', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $subtotal = $collection->subtotalWithoutConditions();

        expect($subtotal)->toBe(3000);
    });

    it('calculates total without conditions', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 2);
        $item2 = new CartItem('item-2', 'Item 2', 15.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $total = $collection->totalWithoutConditions();

        expect($total)->toBe(3500); // (10*2) + 15
    });

    it('converts to formatted array', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 2);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $formatted = $collection->toFormattedArray();

        expect($formatted)->toBeArray();
        expect($formatted)->toHaveKeys(['items', 'total_quantity', 'subtotal', 'total', 'total_without_conditions', 'count', 'is_empty']);
        expect($formatted['count'])->toBe(2);
        expect($formatted['total_quantity'])->toBe(3);
        expect($formatted['is_empty'])->toBeFalse();
    });
});

describe('CartCollection Advanced Filtering', function (): void {
    it('filters items by attribute', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, ['color' => 'red']);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, ['color' => 'blue']);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, ['color' => 'red']);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $redItems = $collection->filterByAttribute('color', 'red');

        expect($redItems->count())->toBe(2);
    });

    it('filters items by attribute existence', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, ['featured' => true]);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, ['featured' => false]);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $featuredItems = $collection->filterByAttribute('featured');

        expect($featuredItems->count())->toBe(2);
    });

    it('searches items by name', function (): void {
        $item1 = new CartItem('item-1', 'Red Widget', 10.00, 1);
        $item2 = new CartItem('item-2', 'Blue Gadget', 20.00, 1);
        $item3 = new CartItem('item-3', 'Red Gizmo', 30.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $results = $collection->searchByName('red');

        expect($results->count())->toBe(2);
        expect($results->pluck('name')->toArray())->toContain('Red Widget');
        expect($results->pluck('name')->toArray())->toContain('Red Gizmo');
    });

    it('filters items by quantity greater than', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 5);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 10);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $results = $collection->whereQuantityGreaterThan(4);

        expect($results->count())->toBe(2);
    });

    it('filters items by quantity less than', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 5);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 10);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $results = $collection->whereQuantityLessThan(6);

        expect($results->count())->toBe(2);
    });

    it('filters items by price range', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 1000, 1);   // $10 in cents
        $item2 = new CartItem('item-2', 'Item 2', 5000, 1);   // $50 in cents
        $item3 = new CartItem('item-3', 'Item 3', 10000, 1);  // $100 in cents

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $results = $collection->wherePriceBetween(2000, 8000);  // $20-$80 in cents

        expect($results->count())->toBe(1);
        expect($results->first()->id)->toBe('item-2');
    });

    it('filters items where quantity is above threshold', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 5);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 10);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $results = $collection->whereQuantityAbove(3);

        expect($results->count())->toBe(2);
    });
});

describe('CartCollection Sorting', function (): void {
    it('sorts items by price ascending', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 50.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 10.00, 1);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $sorted = $collection->sortByPrice('asc');
        $values = $sorted->values();

        expect($values->first()->price)->toBe(1000);  // 10.00 as cents
        expect($values->last()->price)->toBe(5000);   // 50.00 as cents
    });

    it('sorts items by price descending', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 50.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 10.00, 1);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $sorted = $collection->sortByPrice('desc');
        $values = $sorted->values();

        expect($values->first()->price)->toBe(5000);  // 50.00 as cents
        expect($values->last()->price)->toBe(1000);   // 10.00 as cents
    });

    it('sorts items by quantity ascending', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 5);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 2);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 10);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $sorted = $collection->sortByQuantity('asc');
        $values = $sorted->values();

        expect($values->first()->quantity)->toBe(2);
        expect($values->last()->quantity)->toBe(10);
    });

    it('sorts items by quantity descending', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 5);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 2);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 10);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $sorted = $collection->sortByQuantity('desc');
        $values = $sorted->values();

        expect($values->first()->quantity)->toBe(10);
        expect($values->last()->quantity)->toBe(2);
    });

    it('sorts items by name ascending', function (): void {
        $item1 = new CartItem('item-1', 'Zebra', 10.00, 1);
        $item2 = new CartItem('item-2', 'Apple', 20.00, 1);
        $item3 = new CartItem('item-3', 'Mango', 30.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $sorted = $collection->sortByName('asc');
        $values = $sorted->values();

        expect($values->first()->name)->toBe('Apple');
        expect($values->last()->name)->toBe('Zebra');
    });

    it('sorts items by name descending', function (): void {
        $item1 = new CartItem('item-1', 'Zebra', 10.00, 1);
        $item2 = new CartItem('item-2', 'Apple', 20.00, 1);
        $item3 = new CartItem('item-3', 'Mango', 30.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $sorted = $collection->sortByName('desc');
        $values = $sorted->values();

        expect($values->first()->name)->toBe('Zebra');
        expect($values->last()->name)->toBe('Apple');
    });
});

describe('CartCollection Grouping', function (): void {
    it('groups items by attribute', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, ['category' => 'electronics']);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, ['category' => 'books']);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, ['category' => 'electronics']);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $grouped = $collection->groupByAttribute('category');

        expect($grouped->has('electronics'))->toBeTrue();
        expect($grouped->has('books'))->toBeTrue();
        expect($grouped->get('electronics')->count())->toBe(2);
        expect($grouped->get('books')->count())->toBe(1);
    });

    it('groups items by property', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 5);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 2);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 5);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $grouped = $collection->groupByProperty('quantity');

        expect($grouped->has(5))->toBeTrue();
        expect($grouped->has(2))->toBeTrue();
        expect($grouped->get(5)->count())->toBe(2);
    });
});

describe('CartCollection Statistics', function (): void {
    it('gets statistics', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 2);
        $item2 = new CartItem('item-2', 'Item 2', 30.00, 1);
        $item3 = new CartItem('item-3', 'Item 3', 20.00, 3);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $stats = $collection->getStatistics();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKeys([
            'total_items',
            'total_quantity',
            'average_price',
            'highest_price',
            'lowest_price',
            'total_value',
            'total_with_conditions',
            'items_with_conditions',
        ]);
        expect($stats['total_items'])->toBe(3);
        expect($stats['total_quantity'])->toBe(6);
        expect($stats['average_price'])->toEqual(2000);
        expect($stats['highest_price'])->toBe(3000);
        expect($stats['lowest_price'])->toBe(1000);
    });
});

describe('CartCollection Condition Filtering', function (): void {
    it('filters items by condition name', function (): void {
        $discountCondition1 = new CartCondition(
            name: 'discount',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-10%'
        );

        $taxCondition = new CartCondition(
            name: 'tax',
            type: 'tax',
            target: 'items@item_discount/per-item',
            value: '+5%'
        );

        $discountCondition2 = new CartCondition(
            name: 'discount',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-5%'
        );

        $item1 = new CartItem('item-1', 'Item 1', 1000, 1, [], [$discountCondition1]);
        $item2 = new CartItem('item-2', 'Item 2', 2000, 1, [], [$taxCondition]);
        $item3 = new CartItem('item-3', 'Item 3', 3000, 1, [], [$discountCondition2]);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $discounted = $collection->filterByCondition('discount');

        expect($discounted->count())->toBe(2);
        expect($discounted->pluck('id')->toArray())->toContain('item-1');
        expect($discounted->pluck('id')->toArray())->toContain('item-3');
    });

    it('returns empty collection when no items match condition name', function (): void {
        $item = new CartItem('item-1', 'Item 1', 10.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item);

        $filtered = $collection->filterByCondition('nonexistent');

        expect($filtered->isEmpty())->toBeTrue();
    });

    it('filters items by condition type', function (): void {
        $holidaySale = new CartCondition(
            name: 'holiday-sale',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-20%'
        );

        $vat = new CartCondition(
            name: 'vat',
            type: 'tax',
            target: 'items@item_discount/per-item',
            value: '+15%'
        );

        $memberDiscount = new CartCondition(
            name: 'member-discount',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-10%'
        );

        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, [], [$holidaySale]);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, [], [$vat]);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, [], [$memberDiscount]);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $taxed = $collection->filterByConditionType('tax');
        $discounted = $collection->filterByConditionType('discount');

        expect($taxed->count())->toBe(1);
        expect($taxed->first()->id)->toBe('item-2');
        expect($discounted->count())->toBe(2);
    });

    it('filters items by condition target', function (): void {
        $subtotalDiscount = new CartCondition(
            name: 'subtotal-discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%'
        );

        $itemDiscount = new CartCondition(
            name: 'item-discount',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-5%'
        );

        $subtotalTax = new CartCondition(
            name: 'subtotal-tax',
            type: 'tax',
            target: 'cart@cart_subtotal/aggregate',
            value: '+8%'
        );

        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, [], [$subtotalDiscount]);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, [], [$itemDiscount]);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, [], [$subtotalTax]);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $subtotalTargets = $collection->filterByConditionTarget('cart@cart_subtotal/aggregate');
        $itemTargets = $collection->filterByConditionTarget('items@item_discount/per-item');

        expect($subtotalTargets->count())->toBe(2);
        expect($itemTargets->count())->toBe(1);
        expect($itemTargets->first()->id)->toBe('item-2');
    });

    it('filters items by condition value', function (): void {
        $discount1 = new CartCondition(
            name: 'discount-1',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-10%'
        );

        $discount2 = new CartCondition(
            name: 'discount-2',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-5%'
        );

        $discount3 = new CartCondition(
            name: 'discount-3',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-10%'
        );

        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, [], [$discount1]);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, [], [$discount2]);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, [], [$discount3]);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $tenPercent = $collection->filterByConditionValue('-10%');
        $fivePercent = $collection->filterByConditionValue('-5%');

        expect($tenPercent->count())->toBe(2);
        expect($fivePercent->count())->toBe(1);
        expect($fivePercent->first()->id)->toBe('item-2');
    });

    it('filters items by numeric condition value', function (): void {
        $fixedDiscount = new CartCondition(
            name: 'fixed-discount',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: -500
        );

        $fixedTax = new CartCondition(
            name: 'fixed-tax',
            type: 'tax',
            target: 'items@item_discount/per-item',
            value: 350
        );

        $item1 = new CartItem('item-1', 'Item 1', 1000, 1, [], [$fixedDiscount]);
        $item2 = new CartItem('item-2', 'Item 2', 2000, 1, [], [$fixedTax]);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $discount = $collection->filterByConditionValue(-500);
        $tax = $collection->filterByConditionValue(350);

        expect($discount->count())->toBe(1);
        expect($discount->first()->id)->toBe('item-1');
        expect($tax->count())->toBe(1);
        expect($tax->first()->id)->toBe('item-2');
    });

    it('checks if collection has items with conditions', function (): void {
        $discount = new CartCondition(
            name: 'discount',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-10%'
        );

        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, [], [$discount]);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        expect($collection->hasItemsWithConditions())->toBeTrue();
    });

    it('returns false when no items have conditions', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        expect($collection->hasItemsWithConditions())->toBeFalse();
    });

    it('gets total discount amount from all items', function (): void {
        $discount1 = new CartCondition(
            name: 'discount-1',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-10%'
        );

        $discount2 = new CartCondition(
            name: 'discount-2',
            type: 'discount',
            target: 'items@item_discount/per-item',
            value: '-20%'
        );

        $item1 = new CartItem('item-1', 'Item 1', 100.00, 1, [], [$discount1]);
        $item2 = new CartItem('item-2', 'Item 2', 50.00, 1, [], [$discount2]);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $totalDiscount = $collection->getTotalDiscount();

        expect($totalDiscount)->toBeGreaterThan(0);
    });

    it('returns zero discount when no items have discounts', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $totalDiscount = $collection->getTotalDiscount();

        expect($totalDiscount)->toBe(0);
    });
});

describe('CartCollection Model Filtering', function (): void {
    it('filters items by model class', function (): void {
        $product = new class
        {
            public static function getMorphClass(): string
            {
                return 'Product';
            }
        };

        $service = new class
        {
            public static function getMorphClass(): string
            {
                return 'Service';
            }
        };

        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, [], [], $product);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, [], [], $service);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, [], [], $product);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $products = $collection->filterByModel(get_class($product));
        $services = $collection->filterByModel(get_class($service));

        expect($products->count())->toBe(2);
        expect($services->count())->toBe(1);
    });

    it('filters items where model matches using whereModel', function (): void {
        $product = new class
        {
            public static function getMorphClass(): string
            {
                return 'App\\Models\\Product';
            }
        };

        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, [], [], $product);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);

        $results = $collection->whereModel(get_class($product));

        expect($results->count())->toBe(1);
        expect($results->first()->id)->toBe('item-1');
    });

    it('returns empty collection when no items match model', function (): void {
        $item = new CartItem('item-1', 'Item 1', 10.00, 1);

        $collection = new CartCollection;
        $collection->addItem($item);

        $results = $collection->filterByModel('NonExistentModel');

        expect($results->isEmpty())->toBeTrue();
    });
});

describe('CartCollection Unique Operations', function (): void {
    it('gets unique items by property', function (): void {
        $item1 = new CartItem('item-1', 'Item 1', 10.00, 1, ['color' => 'red']);
        $item2 = new CartItem('item-2', 'Item 2', 20.00, 1, ['color' => 'blue']);
        $item3 = new CartItem('item-3', 'Item 3', 30.00, 1, ['color' => 'red']);

        $collection = new CartCollection;
        $collection->addItem($item1);
        $collection->addItem($item2);
        $collection->addItem($item3);

        $unique = $collection->uniqueBy('name');

        expect($unique->count())->toBe(3);
    });
});
