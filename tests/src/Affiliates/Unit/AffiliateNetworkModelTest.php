<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('AffiliateNetwork Model', function (): void {
    beforeEach(function (): void {
        $this->rootAffiliate = Affiliate::create([
            'code' => 'ROOT-001',
            'name' => 'Root Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $this->childAffiliate = Affiliate::create([
            'code' => 'CHILD-001',
            'name' => 'Child Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'parent_affiliate_id' => $this->rootAffiliate->id,
        ]);

        $this->grandchildAffiliate = Affiliate::create([
            'code' => 'GRANDCHILD-001',
            'name' => 'Grandchild Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'parent_affiliate_id' => $this->childAffiliate->id,
        ]);
    });

    it('can be created with required fields', function (): void {
        $network = AffiliateNetwork::create([
            'ancestor_id' => $this->rootAffiliate->id,
            'descendant_id' => $this->rootAffiliate->id,
            'depth' => 0,
        ]);

        expect($network)->toBeInstanceOf(AffiliateNetwork::class)
            ->and($network->ancestor_id)->toBe($this->rootAffiliate->id)
            ->and($network->descendant_id)->toBe($this->rootAffiliate->id)
            ->and($network->depth)->toBe(0);
    });

    it('uses a uuid primary key', function (): void {
        $network = new AffiliateNetwork;

        expect($network->incrementing)->toBeFalse()
            ->and($network->getKeyName())->toBe('id');
    });

    it('does not use timestamps', function (): void {
        $network = new AffiliateNetwork;

        expect($network->timestamps)->toBeFalse();
    });

    it('belongs to ancestor affiliate', function (): void {
        $network = AffiliateNetwork::create([
            'ancestor_id' => $this->rootAffiliate->id,
            'descendant_id' => $this->childAffiliate->id,
            'depth' => 1,
        ]);

        expect($network->ancestor)->toBeInstanceOf(Affiliate::class)
            ->and($network->ancestor->id)->toBe($this->rootAffiliate->id);
    });

    it('belongs to descendant affiliate', function (): void {
        $network = AffiliateNetwork::create([
            'ancestor_id' => $this->rootAffiliate->id,
            'descendant_id' => $this->childAffiliate->id,
            'depth' => 1,
        ]);

        expect($network->descendant)->toBeInstanceOf(Affiliate::class)
            ->and($network->descendant->id)->toBe($this->childAffiliate->id);
    });

    it('casts depth as integer', function (): void {
        $network = AffiliateNetwork::create([
            'ancestor_id' => $this->rootAffiliate->id,
            'descendant_id' => $this->childAffiliate->id,
            'depth' => '3',
        ]);

        expect($network->depth)->toBeInt()
            ->and($network->depth)->toBe(3);
    });

    it('can add affiliate to network without sponsor', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'SOLO-001',
            'name' => 'Solo Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateNetwork::addToNetwork($affiliate);

        $selfReference = AffiliateNetwork::query()
            ->where('ancestor_id', $affiliate->id)
            ->where('descendant_id', $affiliate->id)
            ->first();

        expect($selfReference)->not->toBeNull()
            ->and($selfReference->depth)->toBe(0);
    });

    it('can add affiliate to network with sponsor', function (): void {
        // Add root to network first
        AffiliateNetwork::addToNetwork($this->rootAffiliate);

        $newAffiliate = Affiliate::create([
            'code' => 'NEW-001',
            'name' => 'New Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'parent_affiliate_id' => $this->rootAffiliate->id,
        ]);

        AffiliateNetwork::addToNetwork($newAffiliate, $this->rootAffiliate);

        // Should have self-reference
        $selfReference = AffiliateNetwork::query()
            ->where('ancestor_id', $newAffiliate->id)
            ->where('descendant_id', $newAffiliate->id)
            ->first();

        expect($selfReference)->not->toBeNull()
            ->and($selfReference->depth)->toBe(0);

        // Should have path from root
        $pathFromRoot = AffiliateNetwork::query()
            ->where('ancestor_id', $this->rootAffiliate->id)
            ->where('descendant_id', $newAffiliate->id)
            ->first();

        expect($pathFromRoot)->not->toBeNull()
            ->and($pathFromRoot->depth)->toBe(1);
    });

    it('can get ancestors of affiliate', function (): void {
        // Set up network structure
        AffiliateNetwork::addToNetwork($this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->childAffiliate, $this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->grandchildAffiliate, $this->childAffiliate);

        $ancestors = AffiliateNetwork::getAncestors($this->grandchildAffiliate);

        expect($ancestors)->toHaveCount(2)
            ->and($ancestors->pluck('id')->toArray())->toContain($this->rootAffiliate->id)
            ->and($ancestors->pluck('id')->toArray())->toContain($this->childAffiliate->id);
    });

    it('can get descendants of affiliate', function (): void {
        // Set up network structure
        AffiliateNetwork::addToNetwork($this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->childAffiliate, $this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->grandchildAffiliate, $this->childAffiliate);

        $descendants = AffiliateNetwork::getDescendants($this->rootAffiliate);

        expect($descendants)->toHaveCount(2)
            ->and($descendants->pluck('id')->toArray())->toContain($this->childAffiliate->id)
            ->and($descendants->pluck('id')->toArray())->toContain($this->grandchildAffiliate->id);
    });

    it('can get affiliates at specific depth', function (): void {
        // Set up network structure
        AffiliateNetwork::addToNetwork($this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->childAffiliate, $this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->grandchildAffiliate, $this->childAffiliate);

        $atDepth1 = AffiliateNetwork::getAtDepth($this->rootAffiliate, 1);
        $atDepth2 = AffiliateNetwork::getAtDepth($this->rootAffiliate, 2);

        expect($atDepth1)->toHaveCount(1)
            ->and($atDepth1->first()->id)->toBe($this->childAffiliate->id)
            ->and($atDepth2)->toHaveCount(1)
            ->and($atDepth2->first()->id)->toBe($this->grandchildAffiliate->id);
    });

    it('can get direct children', function (): void {
        // Set up network structure
        AffiliateNetwork::addToNetwork($this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->childAffiliate, $this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->grandchildAffiliate, $this->childAffiliate);

        $directChildren = AffiliateNetwork::getDirectChildren($this->rootAffiliate);

        expect($directChildren)->toHaveCount(1)
            ->and($directChildren->first()->id)->toBe($this->childAffiliate->id);
    });

    it('can get descendant count', function (): void {
        // Set up network structure
        AffiliateNetwork::addToNetwork($this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->childAffiliate, $this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->grandchildAffiliate, $this->childAffiliate);

        $count = AffiliateNetwork::getDescendantCount($this->rootAffiliate);

        expect($count)->toBe(2);
    });

    it('can remove affiliate from network', function (): void {
        // Set up network structure
        AffiliateNetwork::addToNetwork($this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->childAffiliate, $this->rootAffiliate);
        AffiliateNetwork::addToNetwork($this->grandchildAffiliate, $this->childAffiliate);

        AffiliateNetwork::removeFromNetwork($this->childAffiliate);

        // Child and grandchild paths should be removed
        $childPaths = AffiliateNetwork::query()
            ->where('descendant_id', $this->childAffiliate->id)
            ->count();

        $grandchildPaths = AffiliateNetwork::query()
            ->where('descendant_id', $this->grandchildAffiliate->id)
            ->count();

        expect($childPaths)->toBe(0)
            ->and($grandchildPaths)->toBe(0);
    });

    it('uses correct table name from config', function (): void {
        $network = new AffiliateNetwork;

        expect($network->getTable())->toBe('affiliate_network');
    });
});
