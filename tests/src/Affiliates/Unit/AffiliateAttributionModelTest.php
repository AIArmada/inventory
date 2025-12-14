<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

describe('AffiliateAttribution Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'ATTR-TEST-' . uniqid(),
            'name' => 'Attribution Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    it('can be created with required fields', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        expect($attribution)->toBeInstanceOf(AffiliateAttribution::class)
            ->and($attribution->affiliate_code)->toBe($this->affiliate->code)
            ->and($attribution->cart_instance)->toBe('default');
    });

    it('belongs to affiliate', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
        ]);

        expect($attribution->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($attribution->affiliate->id)->toBe($this->affiliate->id);
    });

    it('has many conversions', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
        ]);

        expect($attribution->conversions())->toBeInstanceOf(HasMany::class);
    });

    it('has many touchpoints', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
        ]);

        expect($attribution->touchpoints())->toBeInstanceOf(HasMany::class);
    });

    it('stores UTM parameters', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'source' => 'google',
            'medium' => 'cpc',
            'campaign' => 'summer_sale',
            'term' => 'affiliate marketing',
            'content' => 'banner_ad',
        ]);

        expect($attribution->source)->toBe('google')
            ->and($attribution->medium)->toBe('cpc')
            ->and($attribution->campaign)->toBe('summer_sale')
            ->and($attribution->term)->toBe('affiliate marketing')
            ->and($attribution->content)->toBe('banner_ad');
    });

    it('stores referrer and landing URLs', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'landing_url' => 'https://example.com/product',
            'referrer_url' => 'https://google.com/search',
        ]);

        expect($attribution->landing_url)->toBe('https://example.com/product')
            ->and($attribution->referrer_url)->toBe('https://google.com/search');
    });

    it('stores cookie value', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'cookie_value' => 'aff_cookie_123456',
        ]);

        expect($attribution->cookie_value)->toBe('aff_cookie_123456');
    });

    it('stores voucher code', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'voucher_code' => 'SUMMER20',
        ]);

        expect($attribution->voucher_code)->toBe('SUMMER20');
    });

    it('stores cart identifier', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'cart_identifier' => 'cart_abc123',
        ]);

        expect($attribution->cart_identifier)->toBe('cart_abc123');
    });

    it('casts metadata as array', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'metadata' => ['custom_field' => 'value', 'tracking_id' => '12345'],
        ]);

        expect($attribution->metadata)->toBeArray()
            ->and($attribution->metadata['custom_field'])->toBe('value')
            ->and($attribution->metadata['tracking_id'])->toBe('12345');
    });

    it('casts timestamps correctly', function (): void {
        $now = Carbon::now();
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'last_cookie_seen_at' => $now,
            'expires_at' => $now->copy()->addDays(30),
        ]);

        expect($attribution->first_seen_at)->toBeInstanceOf(Carbon::class)
            ->and($attribution->last_seen_at)->toBeInstanceOf(Carbon::class)
            ->and($attribution->last_cookie_seen_at)->toBeInstanceOf(Carbon::class)
            ->and($attribution->expires_at)->toBeInstanceOf(Carbon::class);
    });

    it('scopes active attributions without expiration', function (): void {
        $active = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'expires_at' => null,
        ]);

        $results = AffiliateAttribution::active()->get();

        expect($results->pluck('id'))->toContain($active->id);
    });

    it('scopes active attributions with future expiration', function (): void {
        $active = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $results = AffiliateAttribution::active()->get();

        expect($results->pluck('id'))->toContain($active->id);
    });

    it('excludes expired attributions from active scope', function (): void {
        $expired = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $results = AffiliateAttribution::active()->get();

        expect($results->pluck('id'))->not->toContain($expired->id);
    });

    it('refreshes last seen timestamp', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'last_seen_at' => Carbon::now()->subHour(),
        ]);

        $originalLastSeen = $attribution->last_seen_at;

        $attribution->refreshLastSeen();
        $attribution->refresh();

        expect($attribution->last_seen_at->gt($originalLastSeen))->toBeTrue();
    });

    it('cascade deletes touchpoints on delete', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
        ]);

        AffiliateTouchpoint::create([
            'affiliate_attribution_id' => $attribution->id,
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'touchpoint_type' => 'click',
        ]);

        $attribution->delete();

        expect(AffiliateTouchpoint::where('affiliate_attribution_id', $attribution->id)->count())->toBe(0);
    });

    it('nulls affiliate_attribution_id on conversions when deleted', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_attribution_id' => $attribution->id,
            'order_reference' => 'ORDER-001',
            'total_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
        ]);

        $attribution->delete();
        $conversion->refresh();

        expect($conversion->affiliate_attribution_id)->toBeNull();
    });

    it('stores user id', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'user_id' => 'user_123',
        ]);

        expect($attribution->user_id)->toBe('user_123');
    });

    it('stores owner morphs', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_instance' => 'default',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user_123',
        ]);

        expect($attribution->owner_type)->toBe('App\\Models\\User')
            ->and($attribution->owner_id)->toBe('user_123');
    });

    it('uses correct table name from config', function (): void {
        $attribution = new AffiliateAttribution;

        expect($attribution->getTable())->toBe('affiliate_attributions');
    });
});
