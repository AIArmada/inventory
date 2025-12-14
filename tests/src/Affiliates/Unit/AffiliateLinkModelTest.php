<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateLink Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'LINK-TEST-' . uniqid(),
            'name' => 'Link Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    it('can be created with required fields', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect($link)->toBeInstanceOf(AffiliateLink::class)
            ->and($link->destination_url)->toBe('https://example.com/product')
            ->and($link->tracking_url)->toBe('https://track.example.com/go/' . $this->affiliate->code);
    });

    it('belongs to affiliate', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect($link->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($link->affiliate->id)->toBe($this->affiliate->id);
    });

    it('belongs to program', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'default_commission_rate' => 1000,
        ]);

        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $program->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect($link->program())->toBeInstanceOf(BelongsTo::class)
            ->and($link->program->id)->toBe($program->id);
    });

    it('increments clicks', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'clicks' => 0,
        ]);

        $link->incrementClicks();
        $link->refresh();

        expect($link->clicks)->toBe(1);

        $link->incrementClicks();
        $link->refresh();

        expect($link->clicks)->toBe(2);
    });

    it('increments conversions', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'conversions' => 0,
        ]);

        $link->incrementConversions();
        $link->refresh();

        expect($link->conversions)->toBe(1);

        $link->incrementConversions();
        $link->refresh();

        expect($link->conversions)->toBe(2);
    });

    it('calculates conversion rate correctly', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'clicks' => 100,
            'conversions' => 5,
        ]);

        expect($link->getConversionRate())->toBe(5.0);
    });

    it('returns zero conversion rate when no clicks', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'clicks' => 0,
            'conversions' => 0,
        ]);

        expect($link->getConversionRate())->toBe(0.0);
    });

    it('returns short url as display url when available', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'short_url' => 'https://short.link/abc',
        ]);

        expect($link->getDisplayUrl())->toBe('https://short.link/abc');
    });

    it('returns tracking url as display url when no short url', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
        ]);

        expect($link->getDisplayUrl())->toBe('https://track.example.com/go/' . $this->affiliate->code);
    });

    it('supports custom slug', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'custom_slug' => 'my-custom-link',
        ]);

        expect($link->custom_slug)->toBe('my-custom-link');
    });

    it('supports campaign tracking', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'campaign' => 'summer_sale',
        ]);

        expect($link->campaign)->toBe('summer_sale');
    });

    it('supports sub IDs for tracking', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'sub_id' => 'facebook',
            'sub_id_2' => 'sidebar_ad',
            'sub_id_3' => 'variant_a',
        ]);

        expect($link->sub_id)->toBe('facebook')
            ->and($link->sub_id_2)->toBe('sidebar_ad')
            ->and($link->sub_id_3)->toBe('variant_a');
    });

    it('casts is_active as boolean', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'is_active' => true,
        ]);

        expect($link->is_active)->toBeBool()->toBeTrue();
    });

    it('casts clicks and conversions as integers', function (): void {
        $link = AffiliateLink::create([
            'affiliate_id' => $this->affiliate->id,
            'destination_url' => 'https://example.com/product',
            'tracking_url' => 'https://track.example.com/go/' . $this->affiliate->code,
            'clicks' => '100',
            'conversions' => '5',
        ]);

        expect($link->clicks)->toBeInt()
            ->and($link->conversions)->toBeInt();
    });

    it('uses correct table name from config', function (): void {
        $link = new AffiliateLink;

        expect($link->getTable())->toBe('affiliate_links');
    });
});
