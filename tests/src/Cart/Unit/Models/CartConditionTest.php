<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Exceptions\InvalidCartConditionException;

it('parses valid and invalid percent values', function (): void {
    // Valid percent (charge/fee)
    $condition = new CartCondition(
        name: 'Percent',
        type: 'fee',
        target: 'cart@cart_subtotal/aggregate',
        value: '25%'
    );
    expect($condition->getValue())->toBe('25%');
    expect($condition->apply(20000))->toBe(25000); // 200 + 25% = 250 (in cents)

    // Valid percent (discount)
    $discountCondition = new CartCondition(
        name: 'Discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-25%'
    );
    expect($discountCondition->getValue())->toBe('-25%');
    expect($discountCondition->apply(20000))->toBe(15000); // 200 - 25% = 150 (in cents)

    // Invalid percent (non-finite)
    expect(fn () => new CartCondition(
        name: 'BadPercent',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '1e309%'
    ))->toThrow(InvalidCartConditionException::class, 'Invalid percentage value: 1e309%');
});

it('throws for non-finite numericValue in parseValue', function (): void {
    // This will trigger the is_finite check and throw at the selected line
    expect(fn () => new CartCondition(
        name: 'TestNonFinite',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '1e309' // Produces INF
    ))->toThrow(InvalidCartConditionException::class, 'Invalid condition value: 1e309');
});

it('can create a cart condition', function (): void {
    $condition = new CartCondition(
        name: 'VAT 12.5%',
        type: 'tax',
        target: 'cart@cart_subtotal/aggregate',
        value: '12.5%'
    );

    expect($condition->getName())->toBe('VAT 12.5%');
    expect($condition->getType())->toBe('tax');
    expect($condition->getTargetDefinition()->toDsl())->toBe('cart@cart_subtotal/aggregate');
    expect($condition->getValue())->toBe('12.5%');
});

it('can create condition with attributes and order', function (): void {
    $condition = new CartCondition(
        name: 'Premium Tax',
        type: 'tax',
        target: 'cart@grand_total/aggregate',
        value: '8.5%',
        attributes: ['jurisdiction' => 'CA', 'code' => 'TAX001'],
        order: 5
    );

    expect($condition->getAttributes())->toBe(['jurisdiction' => 'CA', 'code' => 'TAX001'])
        ->and($condition->getAttribute('jurisdiction'))->toBe('CA')
        ->and($condition->getAttribute('nonexistent', 'default'))->toBe('default')
        ->and($condition->hasAttribute('jurisdiction'))->toBeTrue()
        ->and($condition->hasAttribute('nonexistent'))->toBeFalse()
        ->and($condition->getOrder())->toBe(5);
});

it('can apply percentage discount to value', function (): void {
    $condition = new CartCondition(
        name: '10% Discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%'
    );

    $result = $condition->apply(10000);
    expect($result)->toBe(9000);
});

it('can apply fixed amount discount', function (): void {
    $condition = new CartCondition(
        name: '$5 Discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-5.00'  // $5.00 discount
    );

    $result = $condition->apply(10000);
    expect($result)->toBe(9500);
});

it('can apply percentage charge to value', function (): void {
    $condition = new CartCondition(
        name: '8% Tax',
        type: 'tax',
        target: 'cart@cart_subtotal/aggregate',
        value: '8%'
    );

    $result = $condition->apply(10000);
    expect($result)->toBe(10800);
});

it('can apply fixed amount charge', function (): void {
    $condition = new CartCondition(
        name: 'Shipping Fee',
        type: 'shipping',
        target: 'cart@cart_subtotal/aggregate',
        value: '+15.00'  // $15.00 fee
    );

    $result = $condition->apply(10000);
    expect($result)->toBe(11500);
});

it('identifies discount conditions correctly', function (): void {
    $discount = new CartCondition(
        name: '10% Off',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%'
    );

    $charge = new CartCondition(
        name: 'Tax',
        type: 'tax',
        target: 'cart@cart_subtotal/aggregate',
        value: '8%'
    );

    expect($discount->isDiscount())->toBeTrue();
    expect($discount->isCharge())->toBeFalse();

    expect($charge->isDiscount())->toBeFalse();
    expect($charge->isCharge())->toBeTrue();
});

it('validates condition properties', function (): void {
    expect(fn () => new CartCondition(
        name: '',
        type: 'tax',
        target: 'cart@cart_subtotal/aggregate',
        value: '10%'
    ))->toThrow(InvalidCartConditionException::class, 'Condition name cannot be empty');
});

it('validates condition target', function (): void {
    expect(fn () => new CartCondition(
        name: 'Invalid Target',
        type: 'tax',
        target: 'invalid',
        value: '10%'
    ))->toThrow(InvalidArgumentException::class, 'Malformed target segment [invalid]');
});

it('can create condition from array', function (): void {
    $condition = CartCondition::fromArray([
        'name' => 'Test Condition',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'target_definition' => ConditionTarget::from('cart@cart_subtotal/aggregate')->toArray(),
        'value' => '-10%',
        'attributes' => ['description' => 'Test discount'],
        'order' => 1,
    ]);

    expect($condition->getName())->toBe('Test Condition');
    expect($condition->getType())->toBe('discount');
    expect($condition->getAttribute('description'))->toBe('Test discount');
    expect($condition->getOrder())->toBe(1);
});

it('can convert condition to array', function (): void {
    $condition = new CartCondition(
        name: 'Test Condition',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%',
        attributes: ['description' => 'Test'],
        order: 1
    );

    $array = $condition->toArray();

    expect($array)->toHaveKeys([
        'name', 'type', 'target_definition', 'value', 'attributes', 'order',
        'operator', 'parsed_value', 'is_discount', 'is_charge', 'is_percentage',
    ]);

    expect($array['name'])->toBe('Test Condition');
    expect($array['target_definition']['scope'])->toBe('cart');
    expect($array['is_percentage'])->toBeTrue();
    expect($array['is_discount'])->toBeTrue();
});

it('can get calculated value for display', function (): void {
    $condition = new CartCondition(
        name: '10% Discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%'
    );

    $calculatedValue = $condition->getCalculatedValue(10000);

    expect($calculatedValue)->toBe(-1000);
});

it('identifies percentage-based conditions correctly', function (): void {
    $percentageCondition = new CartCondition(
        name: 'Percentage Tax',
        type: 'tax',
        target: 'cart@grand_total/aggregate',
        value: '8.5%'
    );

    $fixedCondition = new CartCondition(
        name: 'Fixed Fee',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '+15.00'
    );

    expect($percentageCondition->isPercentage())->toBeTrue()
        ->and($fixedCondition->isPercentage())->toBeFalse();
});

it('can create modified copy with with method', function (): void {
    $original = new CartCondition(
        name: 'Original',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%'
    );

    $modified = $original->with(['name' => 'Modified', 'value' => '-20%']);

    expect($modified->getName())->toBe('Modified')
        ->and($modified->getValue())->toBe('-20%')
        ->and($modified->getType())->toBe('discount') // unchanged
        ->and($original->getName())->toBe('Original'); // original unchanged
});

it('can convert to JSON', function (): void {
    $condition = new CartCondition(
        name: 'JSON Test',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-5%'
    );

    $json = $condition->toJson();
    $decoded = json_decode($json, true);

    expect($decoded['name'])->toBe('JSON Test')
        ->and($decoded['value'])->toBe('-5%');
});

it('can be JSON serialized', function (): void {
    $condition = new CartCondition(
        name: 'Serializable',
        type: 'tax',
        target: 'cart@grand_total/aggregate',
        value: '10%'
    );

    $serialized = $condition->jsonSerialize();

    expect($serialized)->toBeArray()
        ->and($serialized['name'])->toBe('Serializable');
});

it('has string representation', function (): void {
    $condition = new CartCondition(
        name: 'Test Condition',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%'
    );

    $string = (string) $condition;

    expect($string)->toBe('Test Condition (discount): -10%');
});

it('validates condition properties on creation', function (): void {
    expect(fn () => new CartCondition(
        name: '',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%'
    ))->toThrow(InvalidCartConditionException::class, 'Condition name cannot be empty');

    expect(fn () => new CartCondition(
        name: 'Valid Name',
        type: '',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%'
    ))->toThrow(InvalidCartConditionException::class, 'Condition type cannot be empty');

    expect(fn () => new CartCondition(
        name: 'Valid Name',
        type: 'discount',
        target: '',
        value: '-10%'
    ))->toThrow(InvalidArgumentException::class, 'Target string cannot be empty.');
});

it('validates condition target values', function (): void {
    expect(fn () => new CartCondition(
        name: 'Invalid Target',
        type: 'discount',
        target: 'invalid_target',
        value: '-10%'
    ))->toThrow(InvalidArgumentException::class, 'Malformed target segment [invalid_target]');
});

it('validates condition value is not empty', function (): void {
    expect(fn () => new CartCondition(
        name: 'Empty Value',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: ''
    ))->toThrow(InvalidCartConditionException::class, 'Condition value cannot be empty');

    // '0' string should be valid because of the specific check in validation
    expect(new CartCondition(
        name: 'Zero String',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '0'
    ))->toBeInstanceOf(CartCondition::class);

    // Note: Integer 0 has validation issues due to strict type checking
    // This is a known limitation in the validation logic
});

it('validates condition values and handles edge cases', function (): void {
    // Non-numeric strings should throw an exception
    expect(fn () => new CartCondition(
        name: 'Alpha String',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: 'abc'
    ))->toThrow(InvalidCartConditionException::class, 'Invalid condition value: abc');

    // Test that normal numeric strings work fine
    expect(new CartCondition(
        name: 'Numeric String',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '25.50'
    ))->toBeInstanceOf(CartCondition::class);
});

it('handles different operators correctly', function (): void {
    $addition = new CartCondition(
        name: 'Add',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '+15.00'
    );

    $subtraction = new CartCondition(
        name: 'Subtract',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-10.00'
    );

    $multiplication = new CartCondition(
        name: 'Multiply',
        type: 'modifier',
        target: 'cart@grand_total/aggregate',
        value: '*1.5'
    );

    $division = new CartCondition(
        name: 'Divide',
        type: 'modifier',
        target: 'cart@grand_total/aggregate',
        value: '/2'
    );

    $noOperator = new CartCondition(
        name: 'No Operator',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '25.00'
    );

    expect($addition->apply(10000))->toBe(11500)
        ->and($subtraction->apply(10000))->toBe(9000)
        ->and($multiplication->apply(10000))->toBe(15000)
        ->and($division->apply(10000))->toBe(5000)
        ->and($noOperator->apply(10000))->toBe(12500); // defaults to addition
});

it('handles division by zero safely', function (): void {
    $divisionByZero = new CartCondition(
        name: 'Divide by Zero',
        type: 'modifier',
        target: 'cart@grand_total/aggregate',
        value: '/0'
    );

    // Should return original value when dividing by zero
    expect($divisionByZero->apply(10000))->toBe(10000);
});

it('creates conditions from array with fromArray method', function (): void {
    $data = [
        'name' => 'Array Condition',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'target_definition' => ConditionTarget::from('cart@cart_subtotal/aggregate')->toArray(),
        'value' => '-15%',
        'attributes' => ['source' => 'coupon'],
        'order' => 3,
    ];

    $condition = CartCondition::fromArray($data);

    expect($condition->getName())->toBe('Array Condition')
        ->and($condition->getType())->toBe('discount')
        ->and($condition->getTargetDefinition()->toDsl())->toBe('cart@cart_subtotal/aggregate')
        ->and($condition->getValue())->toBe('-15%')
        ->and($condition->getAttribute('source'))->toBe('coupon')
        ->and($condition->getOrder())->toBe(3);
});

it('handles positive and negative percentage charges correctly', function (): void {
    $positiveCharge = new CartCondition(
        name: 'Positive Charge',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '10%'
    );

    $negativeDiscount = new CartCondition(
        name: 'Negative Discount',
        type: 'discount',
        target: 'cart@grand_total/aggregate',
        value: '-15%'
    );

    expect($positiveCharge->isCharge())->toBeTrue()
        ->and($positiveCharge->isDiscount())->toBeFalse()
        ->and($negativeDiscount->isDiscount())->toBeTrue()
        ->and($negativeDiscount->isCharge())->toBeFalse();
});

it('throws exception for non-finite condition values', function (): void {
    // String 'INF'
    expect(fn () => new CartCondition(
        name: 'InfiniteStr',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: 'INF'
    ))->toThrow(InvalidCartConditionException::class);

    // String '-INF'
    expect(fn () => new CartCondition(
        name: 'NegativeInfiniteStr',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '-INF'
    ))->toThrow(InvalidCartConditionException::class);

    // String 'NAN'
    expect(fn () => new CartCondition(
        name: 'NaNStr',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: 'NAN'
    ))->toThrow(InvalidCartConditionException::class);

    // Large exponent string (produces INF)
    expect(fn () => new CartCondition(
        name: 'ExponentInf',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '1e309'
    ))->toThrow(InvalidCartConditionException::class);
});

it('can get rules and check isDynamic', function (): void {
    $static = new CartCondition(
        name: 'Static',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '+5'
    );
    expect($static->getRules())->toBeNull()
        ->and($static->isDynamic())->toBeFalse();

    $dynamic = new CartCondition(
        name: 'Dynamic',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '+5',
        rules: [fn () => true]
    );
    expect($dynamic->getRules())->toBeArray()
        ->and($dynamic->isDynamic())->toBeTrue();
});

it('shouldApply returns true for static and evaluates rules for dynamic', function (): void {
    $static = new CartCondition(
        name: 'Static',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '+5'
    );
    $cart = new AIArmada\Cart\Cart(
        storage: new class implements AIArmada\Cart\Storage\StorageInterface
        {
            public function get(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function put(string $identifier, string $instance, string $content): void {}

            public function has(string $identifier, string $instance): bool
            {
                return false;
            }

            public function forget(string $identifier, string $instance): void {}

            public function flush(): void {}

            public function getInstances(string $identifier): array
            {
                return [];
            }

            public function forgetIdentifier(string $identifier): void {}

            public function getItems(string $identifier, string $instance): array
            {
                return [];
            }

            public function getConditions(string $identifier, string $instance): array
            {
                return [];
            }

            public function putItems(string $identifier, string $instance, array $items): void {}

            public function putConditions(string $identifier, string $instance, array $conditions): void {}

            public function putBoth(string $identifier, string $instance, array $items, array $conditions): void {}

            public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void {}

            public function putMetadataBatch(string $identifier, string $instance, array $metadata): void {}

            public function getMetadata(string $identifier, string $instance, string $key): mixed
            {
                return null;
            }

            public function clearMetadata(string $identifier, string $instance): void {}

            public function clearAll(string $identifier, string $instance): void {}

            public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
            {
                return false;
            }

            public function getVersion(string $identifier, string $instance): ?int
            {
                return null;
            }

            public function getId(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function getAllMetadata(string $identifier, string $instance): array
            {
                return [];
            }

            public function getCreatedAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function getUpdatedAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function withTenantId(?string $tenantId): static
            {
                return $this;
            }

            public function getTenantId(): ?string
            {
                return null;
            }

            public function withOwner(?Illuminate\Database\Eloquent\Model $owner): static
            {
                return $this;
            }

            public function getOwnerType(): ?string
            {
                return null;
            }

            public function getOwnerId(): string | int | null
            {
                return null;
            }

            // AI/Analytics - Abandonment Tracking
            public function getExpiresAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function isExpired(string $identifier, string $instance): bool
            {
                return false;
            }

            public function getLastActivityAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function touchLastActivity(string $identifier, string $instance): void {}

            public function getCheckoutStartedAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function markCheckoutStarted(string $identifier, string $instance): void {}

            public function getCheckoutAbandonedAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function markCheckoutAbandoned(string $identifier, string $instance): void {}

            public function getRecoveryAttempts(string $identifier, string $instance): int
            {
                return 0;
            }

            public function incrementRecoveryAttempts(string $identifier, string $instance): void {}

            public function getRecoveredAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function markRecovered(string $identifier, string $instance): void {}

            public function clearAbandonmentTracking(string $identifier, string $instance): void {}

            // Event Sourcing Support
            public function getEventStreamPosition(string $identifier, string $instance): int
            {
                return 0;
            }

            public function setEventStreamPosition(string $identifier, string $instance, int $position): void {}

            public function getAggregateVersion(string $identifier, string $instance): string
            {
                return '0';
            }

            public function setAggregateVersion(string $identifier, string $instance, string $version): void {}

            public function getSnapshotAt(string $identifier, string $instance): ?string
            {
                return null;
            }

            public function markSnapshotTaken(string $identifier, string $instance): void {}
        },
        identifier: 'test-user'
    );
    $item = new AIArmada\Cart\Models\CartItem(
        id: 'test',
        name: 'Test',
        price: 1.0,
        quantity: 1
    );
    expect($static->shouldApply($cart, $item))->toBeTrue();

    $dynamicTrue = new CartCondition(
        name: 'DynamicTrue',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '+5',
        rules: [fn () => true]
    );
    expect($dynamicTrue->shouldApply($cart, $item))->toBeTrue();

    $dynamicFalse = new CartCondition(
        name: 'DynamicFalse',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '+5',
        rules: [fn () => false]
    );
    expect($dynamicFalse->shouldApply($cart, $item))->toBeFalse();
});
