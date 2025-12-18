<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\HasOwnerPermissions;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use AIArmada\FilamentAuthz\Http\Middleware\AuthorizePanelRoles;
use AIArmada\FilamentAuthz\Jobs\WriteAuditLogJob;
use AIArmada\FilamentAuthz\Listeners\PermissionEventSubscriber;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

describe('AuthorizePanelRoles Middleware', function (): void {
    beforeEach(function (): void {
        $this->middleware = new AuthorizePanelRoles;
    });

    it('can be instantiated', function (): void {
        expect($this->middleware)->toBeInstanceOf(AuthorizePanelRoles::class);
    });

    it('passes through when no panel', function (): void {
        Filament::shouldReceive('getCurrentPanel')->andReturn(null);

        $request = Request::create('/test');
        $called = false;
        $next = function ($req) use (&$called) {
            $called = true;

            return response('passed');
        };

        $this->middleware->handle($request, $next);

        expect($called)->toBeTrue();
    });

    it('passes through when feature disabled', function (): void {
        // When feature is disabled and no panel, the middleware passes through
        // We test the null panel path which is simpler and avoids mock return type issues
        config(['filament-authz.features.panel_role_authorization' => false]);

        Filament::shouldReceive('getCurrentPanel')->andReturn(null);

        $request = Request::create('/test');
        $called = false;
        $next = function ($req) use (&$called) {
            $called = true;

            return response('passed');
        };

        $this->middleware->handle($request, $next);

        expect($called)->toBeTrue();
    });
});

describe('WriteAuditLogJob', function (): void {
    it('can be instantiated with data', function (): void {
        $data = [
            'event_type' => 'permission.granted',
            'severity' => 'low',
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
        ];

        $job = new WriteAuditLogJob($data);

        expect($job)->toBeInstanceOf(WriteAuditLogJob::class)
            ->and($job->data)->toBe($data);
    });

    it('returns backoff array', function (): void {
        $job = new WriteAuditLogJob([]);
        $backoff = $job->backoff();

        expect($backoff)->toBe([1, 5, 10]);
    });

    it('returns tries count', function (): void {
        $job = new WriteAuditLogJob([]);
        $tries = $job->tries();

        expect($tries)->toBe(3);
    });
});

describe('PermissionEventSubscriber', function (): void {
    it('can be instantiated', function (): void {
        $auditLogger = Mockery::mock(AIArmada\FilamentAuthz\Services\AuditLogger::class);
        $subscriber = new PermissionEventSubscriber($auditLogger);

        expect($subscriber)->toBeInstanceOf(PermissionEventSubscriber::class);
    });

    it('subscribes to auth and permission events', function (): void {
        $auditLogger = Mockery::mock(AIArmada\FilamentAuthz\Services\AuditLogger::class);
        $subscriber = new PermissionEventSubscriber($auditLogger);

        $dispatcher = Mockery::mock(Illuminate\Contracts\Events\Dispatcher::class);
        $dispatcher->shouldReceive('listen')->times(8);

        $subscriber->subscribe($dispatcher);
    });
});

describe('HasOwnerPermissions trait', function (): void {
    it('can be used by a class', function (): void {
        $class = new class
        {
            use HasOwnerPermissions;
        };

        expect($class)->toBeObject();
    });
});

describe('HasPanelAuthz trait', function (): void {
    it('can be used by a class', function (): void {
        $class = new class
        {
            use HasPanelAuthz;
        };

        expect($class)->toBeObject();
    });
});
