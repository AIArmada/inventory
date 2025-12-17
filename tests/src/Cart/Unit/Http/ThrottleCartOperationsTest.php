<?php

declare(strict_types=1);

use AIArmada\Cart\Http\Middleware\ThrottleCartOperations;
use AIArmada\Cart\Security\CartRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

describe('ThrottleCartOperations', function (): void {
    it('can be instantiated with real rate limiter', function (): void {
        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        expect($middleware)->toBeInstanceOf(ThrottleCartOperations::class);
    });

    it('allows request when rate limit is disabled', function (): void {
        config(['cart.rate_limiting.enabled' => false]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart', 'GET');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    it('processes request when within rate limit', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart', 'GET');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    it('resolves checkout operation from path', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart/checkout', 'POST');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('checkout'));

        expect($response->getContent())->toBe('checkout');
    });

    it('resolves merge operation from path', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart/merge', 'POST');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('merged'));

        expect($response->getContent())->toBe('merged');
    });

    it('resolves clear operation from path', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart/clear', 'DELETE');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('cleared'));

        expect($response->getContent())->toBe('cleared');
    });

    it('resolves condition add operation', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart/condition', 'POST');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('condition added'));

        expect($response->getContent())->toBe('condition added');
    });

    it('resolves condition remove operation', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart/condition/promo10', 'DELETE');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('condition removed'));

        expect($response->getContent())->toBe('condition removed');
    });

    it('resolves item add operation', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart/item', 'POST');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('item added'));

        expect($response->getContent())->toBe('item added');
    });

    it('resolves item update operation with PUT', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart/item/123', 'PUT');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('item updated'));

        expect($response->getContent())->toBe('item updated');
    });

    it('resolves item remove operation', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart/item/123', 'DELETE');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('item removed'));

        expect($response->getContent())->toBe('item removed');
    });

    it('uses custom operation parameter when provided', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/custom/endpoint', 'GET');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('custom'), 'custom_operation');

        expect($response->getContent())->toBe('custom');
    });

    it('adds rate limit headers to response', function (): void {
        config(['cart.rate_limiting.enabled' => true]);

        $rateLimiter = new CartRateLimiter;
        $middleware = new ThrottleCartOperations($rateLimiter);

        $request = Request::create('/cart', 'GET');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn() => new Response('OK'));

        expect($response->headers->has('X-RateLimit-Remaining'))->toBeTrue();
    });
});
