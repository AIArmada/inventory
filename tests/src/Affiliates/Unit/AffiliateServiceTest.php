<?php

declare(strict_types=1);

use AIArmada\Affiliates\Events\AffiliateAttributed;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Support\Middleware\TrackAffiliateCookie;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use AIArmada\Cart\Facades\Cart;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'AFFILIATE42',
        'name' => 'Launch Partner',
        'description' => 'Test affiliate partner',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500, // 5%
        'currency' => 'USD',
    ]);
});

test('cart metadata and attribution are created when attaching an affiliate code', function (): void {
    Cart::attachAffiliate($this->affiliate->code, [
        'source' => 'newsletter',
        'utm_campaign' => 'spring',
    ]);

    $metadata = Cart::getMetadata('affiliate');

    expect($metadata)
        ->toBeArray()
        ->and($metadata['affiliate_code'])->toBe($this->affiliate->code)
        ->and($metadata['source'])->toBe('newsletter')
        ->and(AffiliateAttribution::count())->toBe(1);
});

test('affiliate conversions are recorded using cart metadata', function (): void {
    Cart::attachAffiliate($this->affiliate->code);

    $conversion = Cart::recordAffiliateConversion([
        'order_reference' => 'SO-1001',
        'subtotal' => 10000,
        'total' => 12000,
    ]);

    expect($conversion)
        ->not()->toBeNull()
        ->and(AffiliateConversion::count())->toBe(1);

    $stored = AffiliateConversion::firstOrFail();

    expect($stored->affiliate_code)
        ->toBe($this->affiliate->code)
        ->and($stored->commission_minor)->toBe(500) // 5% of 10,000
        ->and($stored->order_reference)->toBe('SO-1001');
});

test('affiliate metadata helpers expose and clear attachment state', function (): void {
    Cart::attachAffiliate($this->affiliate->code);

    expect(Cart::getAffiliateMetadata('affiliate_code'))->toBe($this->affiliate->code);

    $service = app(AffiliateService::class);
    $cart = app('cart')->getCurrentCart();

    expect($service->getAttachedAffiliate($cart))->not()->toBeNull();

    Cart::detachAffiliate();

    expect($service->getAttachedAffiliate($cart))->toBeNull()
        ->and($cart->hasMetadata('affiliate'))->toBeFalse();
});

test('recording conversion without metadata returns null', function (): void {
    expect(Cart::recordAffiliateConversion(['order_reference' => 'SO-404']))
        ->toBeNull();

    expect(AffiliateConversion::count())->toBe(0);
});

test('events are dispatched for attribution and conversion flows', function (): void {
    Event::fake([
        AffiliateAttributed::class,
        AffiliateConversionRecorded::class,
    ]);

    Cart::attachAffiliate($this->affiliate->code);
    Cart::recordAffiliateConversion(['subtotal' => 2000]);

    Event::assertDispatched(
        AffiliateAttributed::class,
        fn (AffiliateAttributed $event): bool => $event->affiliate->code === $this->affiliate->code
    );

    Event::assertDispatched(
        AffiliateConversionRecorded::class,
        fn (AffiliateConversionRecorded $event): bool => $event->conversion->affiliateCode === $this->affiliate->code
    );
});

test('middleware captures affiliate visits via cookies', function (): void {
    $cookieName = config('affiliates.cookies.name', 'affiliate_session');
    $request = Request::create('/checkout?aff=' . $this->affiliate->code . '&utm_source=newsletter', 'GET');

    app()->instance('request', $request);

    $middleware = app(TrackAffiliateCookie::class);
    $response = $middleware->handle($request, fn () => response('ok'));

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === $cookieName);

    expect($cookie)->not()->toBeNull();

    $attribution = AffiliateAttribution::first();

    expect($attribution)
        ->not()->toBeNull()
        ->and($attribution->cookie_value)->not()->toBeNull()
        ->and($attribution->cart_identifier)->toBeNull();
});

test('cart metadata hydrates from affiliate cookie automatically', function (): void {
    $cookieName = config('affiliates.cookies.name', 'affiliate_session');
    $request = Request::create('/landing?aff=' . $this->affiliate->code, 'GET');

    app()->instance('request', $request);

    $middleware = app(TrackAffiliateCookie::class);
    $response = $middleware->handle($request, fn () => response('ok'));

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === $cookieName);

    expect($cookie)->not()->toBeNull();

    $nextRequest = Request::create('/cart');
    $nextRequest->cookies->set($cookieName, $cookie?->getValue());
    app()->instance('request', $nextRequest);

    $cart = app('cart')->getCurrentCart();

    $metadata = $cart->getMetadata('affiliate');

    expect($metadata)
        ->toBeArray()
        ->and($metadata['affiliate_code'])->toBe($this->affiliate->code);
});

test('affiliate cookies honor owner scoping', function (): void {
    config([
        'affiliates.owner.enabled' => true,
        'affiliates.tracking.block_self_referral' => false,
    ]);

    app()->singleton(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new StaticOwnerResolver);

    $ownerOne = AffiliateTestOwner::create(['id' => (string) Str::uuid(), 'name' => 'Owner One']);
    $ownerTwo = AffiliateTestOwner::create(['id' => (string) Str::uuid(), 'name' => 'Owner Two']);

    StaticOwnerResolver::$owner = $ownerOne;

    $affiliate = Affiliate::create([
        'code' => 'OWNED-AFF',
        'name' => 'Scoped Partner',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 250,
        'currency' => 'USD',
    ]);

    $cookieName = config('affiliates.cookies.name', 'affiliate_session');
    $request = Request::create('/touch?aff=' . $affiliate->code, 'GET');
    app()->instance('request', $request);

    $middleware = app(TrackAffiliateCookie::class);
    $response = $middleware->handle($request, fn () => response('ok'));

    $cookieValue = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === $cookieName)
        ?->getValue();

    expect($cookieValue)->not()->toBeNull();

    StaticOwnerResolver::$owner = $ownerTwo;

    $nextRequest = Request::create('/cart');
    $nextRequest->cookies->set($cookieName, $cookieValue);
    app()->instance('request', $nextRequest);

    $cart = app('cart')->getCurrentCart();
    expect($cart->getMetadata('affiliate'))->toBeNull();

    StaticOwnerResolver::$owner = $ownerOne;
    app()->instance('request', $nextRequest);

    $cartWithOwner = app('cart')->getCurrentCart();
    expect($cartWithOwner->getMetadata('affiliate')['affiliate_code'] ?? null)->toBe($affiliate->code);
});

test('self referral is blocked when owner matches current owner', function (): void {
    config([
        'affiliates.owner.enabled' => true,
        'affiliates.tracking.block_self_referral' => true,
    ]);

    app()->singleton(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new StaticOwnerResolver);

    $owner = AffiliateTestOwner::create(['id' => (string) Str::uuid(), 'name' => 'Owner Self']);
    StaticOwnerResolver::$owner = $owner;

    $selfAffiliate = Affiliate::create([
        'code' => 'SELF',
        'name' => 'Self Owned',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
    ]);

    Cart::attachAffiliate($selfAffiliate->code);

    expect(AffiliateAttribution::count())->toBe(0);
});

test('consent is required when configured', function (): void {
    config(['affiliates.cookies.require_consent' => true]);

    $cookieName = config('affiliates.cookies.name', 'affiliate_session');
    $request = Request::create('/landing?aff=' . $this->affiliate->code, 'GET');
    app()->instance('request', $request);

    $middleware = app(TrackAffiliateCookie::class);
    $response = $middleware->handle($request, fn () => response('ok'));

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === $cookieName);

    expect($cookie)->toBeNull()
        ->and(AffiliateAttribution::count())->toBe(0);

    $consentedRequest = Request::create('/landing?aff=' . $this->affiliate->code . '&affiliate_consent=1', 'GET');
    app()->instance('request', $consentedRequest);

    $responseWithConsent = $middleware->handle($consentedRequest, fn () => response('ok'));

    $cookieWithConsent = collect($responseWithConsent->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === $cookieName);

    expect($cookieWithConsent)->not()->toBeNull()
        ->and(AffiliateAttribution::count())->toBe(1);
});

test('rate limiting blocks excessive attributions from the same IP', function (): void {
    config([
        'affiliates.tracking.ip_rate_limit' => [
            'enabled' => true,
            'max' => 1,
            'decay_minutes' => 60,
        ],
    ]);

    $service = app(AffiliateService::class);

    $first = $service->trackVisitByCode($this->affiliate->code, ['ip_address' => '10.0.0.1']);
    $second = $service->trackVisitByCode($this->affiliate->code, ['ip_address' => '10.0.0.1']);

    expect($first)->not()->toBeNull();
    expect($second)->toBeNull();
});

test('webhook dispatcher is invoked for attribution and conversion', function (): void {
    config([
        'affiliates.events.dispatch_webhooks' => true,
        'affiliates.commissions.auto_approve' => true,
    ]);

    $fakeDispatcher = new FakeWebhookDispatcher;
    app()->instance(WebhookDispatcher::class, $fakeDispatcher);
    app()->forgetInstance(AffiliateService::class);

    Cart::attachAffiliate($this->affiliate->code);
    Cart::recordAffiliateConversion(['subtotal' => 5000]);

    expect($fakeDispatcher->events)
        ->toHaveCount(2)
        ->and($fakeDispatcher->events[0]['type'])->toBe('attribution')
        ->and($fakeDispatcher->events[1]['type'])->toBe('conversion');
});

class StaticOwnerResolver implements OwnerResolverInterface
{
    public static ?Model $owner = null;

    public function resolve(): ?Model
    {
        return self::$owner;
    }
}

class AffiliateTestOwner extends Model
{
    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}

class FakeWebhookDispatcher extends WebhookDispatcher
{
    /** @var array<int, array{type:string,payload:array}> */
    public array $events = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $type, array $payload): void
    {
        $this->events[] = ['type' => $type, 'payload' => $payload];
    }
}
