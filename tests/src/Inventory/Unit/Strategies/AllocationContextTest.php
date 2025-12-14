<?php

declare(strict_types=1);

use AIArmada\Inventory\Strategies\AllocationContext;

describe('AllocationContext', function (): void {
    describe('constructor', function (): void {
        it('creates with defaults', function (): void {
            $context = new AllocationContext();

            expect($context->locationId)->toBeNull();
            expect($context->orderId)->toBeNull();
            expect($context->customerId)->toBeNull();
            expect($context->excludeExpiringSoon)->toBeFalse();
            expect($context->minDaysToExpiry)->toBe(7);
            expect($context->allowPartialAllocation)->toBeTrue();
            expect($context->createBackorderForShortfall)->toBeFalse();
            expect($context->maxLocations)->toBeNull();
            expect($context->preferSameLocation)->toBeTrue();
            expect($context->preferWholeBatches)->toBeFalse();
        });

        it('creates with custom values', function (): void {
            $context = new AllocationContext(
                locationId: 'loc-123',
                orderId: 'order-456',
                customerId: 'cust-789',
                excludeExpiringSoon: true,
                minDaysToExpiry: 14,
                allowPartialAllocation: false,
                createBackorderForShortfall: true,
                originX: 1.5,
                originY: 2.5,
                originZ: 3.5,
                maxLocations: 3,
                preferSameLocation: false,
                preferWholeBatches: true,
                preferredZone: 'zone-a',
                excludeZone: 'zone-b',
                excludeLocationIds: ['loc-1', 'loc-2'],
                preferLocationIds: ['loc-3', 'loc-4'],
            );

            expect($context->locationId)->toBe('loc-123');
            expect($context->orderId)->toBe('order-456');
            expect($context->customerId)->toBe('cust-789');
            expect($context->excludeExpiringSoon)->toBeTrue();
            expect($context->minDaysToExpiry)->toBe(14);
            expect($context->allowPartialAllocation)->toBeFalse();
            expect($context->createBackorderForShortfall)->toBeTrue();
            expect($context->originX)->toBe(1.5);
            expect($context->originY)->toBe(2.5);
            expect($context->originZ)->toBe(3.5);
            expect($context->maxLocations)->toBe(3);
            expect($context->preferSameLocation)->toBeFalse();
            expect($context->preferWholeBatches)->toBeTrue();
            expect($context->preferredZone)->toBe('zone-a');
            expect($context->excludeZone)->toBe('zone-b');
            expect($context->excludeLocationIds)->toBe(['loc-1', 'loc-2']);
            expect($context->preferLocationIds)->toBe(['loc-3', 'loc-4']);
        });
    });

    describe('forOrder', function (): void {
        it('creates context for order', function (): void {
            $context = AllocationContext::forOrder('order-123');

            expect($context->orderId)->toBe('order-123');
            expect($context->customerId)->toBeNull();
            expect($context->createBackorderForShortfall)->toBeTrue();
        });

        it('creates context for order with customer', function (): void {
            $context = AllocationContext::forOrder('order-123', 'cust-456');

            expect($context->orderId)->toBe('order-123');
            expect($context->customerId)->toBe('cust-456');
            expect($context->createBackorderForShortfall)->toBeTrue();
        });
    });

    describe('forLocation', function (): void {
        it('creates context for specific location', function (): void {
            $context = AllocationContext::forLocation('loc-123');

            expect($context->locationId)->toBe('loc-123');
        });
    });

    describe('forPerishables', function (): void {
        it('creates context with default expiry constraint', function (): void {
            $context = AllocationContext::forPerishables();

            expect($context->excludeExpiringSoon)->toBeTrue();
            expect($context->minDaysToExpiry)->toBe(7);
        });

        it('creates context with custom expiry constraint', function (): void {
            $context = AllocationContext::forPerishables(30);

            expect($context->excludeExpiringSoon)->toBeTrue();
            expect($context->minDaysToExpiry)->toBe(30);
        });
    });

    describe('fromCoordinates', function (): void {
        it('creates context from 2D coordinates', function (): void {
            $context = AllocationContext::fromCoordinates(10.5, 20.5);

            expect($context->originX)->toBe(10.5);
            expect($context->originY)->toBe(20.5);
            expect($context->originZ)->toBeNull();
        });

        it('creates context from 3D coordinates', function (): void {
            $context = AllocationContext::fromCoordinates(10.5, 20.5, 30.5);

            expect($context->originX)->toBe(10.5);
            expect($context->originY)->toBe(20.5);
            expect($context->originZ)->toBe(30.5);
        });
    });

    describe('withLocation', function (): void {
        it('returns new instance with location', function (): void {
            $original = new AllocationContext();
            $modified = $original->withLocation('loc-123');

            expect($modified)->not->toBe($original);
            expect($modified->locationId)->toBe('loc-123');
            expect($original->locationId)->toBeNull();
        });
    });

    describe('withOrder', function (): void {
        it('returns new instance with order', function (): void {
            $original = new AllocationContext();
            $modified = $original->withOrder('order-123');

            expect($modified)->not->toBe($original);
            expect($modified->orderId)->toBe('order-123');
            expect($original->orderId)->toBeNull();
        });
    });

    describe('withBackorderSupport', function (): void {
        it('returns new instance with backorder enabled', function (): void {
            $original = new AllocationContext();
            $modified = $original->withBackorderSupport();

            expect($modified)->not->toBe($original);
            expect($modified->createBackorderForShortfall)->toBeTrue();
            expect($original->createBackorderForShortfall)->toBeFalse();
        });
    });

    describe('withExpiryConstraint', function (): void {
        it('returns new instance with default expiry', function (): void {
            $original = new AllocationContext();
            $modified = $original->withExpiryConstraint();

            expect($modified)->not->toBe($original);
            expect($modified->excludeExpiringSoon)->toBeTrue();
            expect($modified->minDaysToExpiry)->toBe(7);
        });

        it('returns new instance with custom expiry', function (): void {
            $original = new AllocationContext();
            $modified = $original->withExpiryConstraint(21);

            expect($modified)->not->toBe($original);
            expect($modified->excludeExpiringSoon)->toBeTrue();
            expect($modified->minDaysToExpiry)->toBe(21);
        });
    });

    describe('withMaxLocations', function (): void {
        it('returns new instance with max locations', function (): void {
            $original = new AllocationContext();
            $modified = $original->withMaxLocations(5);

            expect($modified)->not->toBe($original);
            expect($modified->maxLocations)->toBe(5);
            expect($original->maxLocations)->toBeNull();
        });
    });

    describe('withPreferredZone', function (): void {
        it('returns new instance with preferred zone', function (): void {
            $original = new AllocationContext();
            $modified = $original->withPreferredZone('zone-a');

            expect($modified)->not->toBe($original);
            expect($modified->preferredZone)->toBe('zone-a');
            expect($original->preferredZone)->toBeNull();
        });
    });

    describe('excludingZone', function (): void {
        it('returns new instance with excluded zone', function (): void {
            $original = new AllocationContext();
            $modified = $original->excludingZone('zone-b');

            expect($modified)->not->toBe($original);
            expect($modified->excludeZone)->toBe('zone-b');
            expect($original->excludeZone)->toBeNull();
        });
    });

    describe('excludingLocations', function (): void {
        it('returns new instance with excluded locations', function (): void {
            $original = new AllocationContext();
            $modified = $original->excludingLocations(['loc-1', 'loc-2']);

            expect($modified)->not->toBe($original);
            expect($modified->excludeLocationIds)->toBe(['loc-1', 'loc-2']);
            expect($original->excludeLocationIds)->toBeNull();
        });
    });

    describe('preferringLocations', function (): void {
        it('returns new instance with preferred locations', function (): void {
            $original = new AllocationContext();
            $modified = $original->preferringLocations(['loc-3', 'loc-4']);

            expect($modified)->not->toBe($original);
            expect($modified->preferLocationIds)->toBe(['loc-3', 'loc-4']);
            expect($original->preferLocationIds)->toBeNull();
        });
    });

    describe('hasOriginCoordinates', function (): void {
        it('returns false when no coordinates', function (): void {
            $context = new AllocationContext();

            expect($context->hasOriginCoordinates())->toBeFalse();
        });

        it('returns false when only X coordinate', function (): void {
            $context = new AllocationContext(originX: 1.0);

            expect($context->hasOriginCoordinates())->toBeFalse();
        });

        it('returns false when only Y coordinate', function (): void {
            $context = new AllocationContext(originY: 1.0);

            expect($context->hasOriginCoordinates())->toBeFalse();
        });

        it('returns true when both X and Y coordinates', function (): void {
            $context = new AllocationContext(originX: 1.0, originY: 2.0);

            expect($context->hasOriginCoordinates())->toBeTrue();
        });

        it('returns true with all three coordinates', function (): void {
            $context = AllocationContext::fromCoordinates(1.0, 2.0, 3.0);

            expect($context->hasOriginCoordinates())->toBeTrue();
        });
    });

    describe('method chaining', function (): void {
        it('supports fluent interface', function (): void {
            $context = (new AllocationContext())
                ->withLocation('loc-123')
                ->withOrder('order-456')
                ->withBackorderSupport()
                ->withExpiryConstraint(14)
                ->withMaxLocations(3)
                ->withPreferredZone('zone-a')
                ->excludingZone('zone-b')
                ->excludingLocations(['loc-x'])
                ->preferringLocations(['loc-y']);

            expect($context->locationId)->toBe('loc-123');
            expect($context->orderId)->toBe('order-456');
            expect($context->createBackorderForShortfall)->toBeTrue();
            expect($context->excludeExpiringSoon)->toBeTrue();
            expect($context->minDaysToExpiry)->toBe(14);
            expect($context->maxLocations)->toBe(3);
            expect($context->preferredZone)->toBe('zone-a');
            expect($context->excludeZone)->toBe('zone-b');
            expect($context->excludeLocationIds)->toBe(['loc-x']);
            expect($context->preferLocationIds)->toBe(['loc-y']);
        });
    });
});
