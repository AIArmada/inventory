<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\RelationManagers\ItemsRelationManager;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\RelationManagers\RatesRelationManager;

uses(TestCase::class);

// ============================================
// Relation Managers Tests
// ============================================

describe('RatesRelationManager', function (): void {
    it('can be instantiated', function (): void {
        $manager = new RatesRelationManager;

        expect($manager)->toBeInstanceOf(RatesRelationManager::class);
    });

    it('has correct relationship name', function (): void {
        $reflection = new ReflectionProperty(RatesRelationManager::class, 'relationship');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('rates');
    });

    it('has correct record title attribute', function (): void {
        $reflection = new ReflectionProperty(RatesRelationManager::class, 'recordTitleAttribute');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('name');
    });
});

describe('ReturnAuthorizationItemsRelationManager', function (): void {
    it('can be instantiated', function (): void {
        $manager = new ItemsRelationManager;

        expect($manager)->toBeInstanceOf(ItemsRelationManager::class);
    });

    it('has correct relationship name', function (): void {
        $reflection = new ReflectionProperty(ItemsRelationManager::class, 'relationship');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('items');
    });

    it('has correct record title attribute', function (): void {
        $reflection = new ReflectionProperty(ItemsRelationManager::class, 'recordTitleAttribute');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('name');
    });
});
