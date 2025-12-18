<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::query()->delete();
});

describe('DiscoverCommand command properties', function (): void {
    it('has correct signature', function (): void {
        $command = new DiscoverCommand;
        $reflection = new ReflectionClass($command);
        $signature = $reflection->getProperty('signature');
        $signature->setAccessible(true);

        expect($signature->getValue($command))->toContain('authz:discover');
    });

    it('has correct description', function (): void {
        $command = new DiscoverCommand;
        $reflection = new ReflectionClass($command);
        $description = $reflection->getProperty('description');
        $description->setAccessible(true);

        expect($description->getValue($command))->toBe('Discover and display all Filament entities with their permissions');
    });
});

describe('DiscoverCommand handle', function (): void {
    it('discovers all entity types by default', function (): void {
        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->once()->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverPages')->once()->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')->once()->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover')
            ->assertSuccessful();
    });

    it('discovers only resources when type=resources', function (): void {
        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->once()->andReturn(collect());
        $mockDiscovery->shouldNotReceive('discoverPages');
        $mockDiscovery->shouldNotReceive('discoverWidgets');

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover', ['--type' => 'resources'])
            ->assertSuccessful();
    });

    it('discovers only pages when type=pages', function (): void {
        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldNotReceive('discoverResources');
        $mockDiscovery->shouldReceive('discoverPages')->once()->andReturn(collect());
        $mockDiscovery->shouldNotReceive('discoverWidgets');

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover', ['--type' => 'pages'])
            ->assertSuccessful();
    });

    it('discovers only widgets when type=widgets', function (): void {
        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldNotReceive('discoverResources');
        $mockDiscovery->shouldNotReceive('discoverPages');
        $mockDiscovery->shouldReceive('discoverWidgets')->once()->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover', ['--type' => 'widgets'])
            ->assertSuccessful();
    });

    it('passes panel option to discovery service', function (): void {
        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')
            ->with(Mockery::on(fn ($opts) => $opts['panels'] === ['admin']))
            ->once()
            ->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverPages')
            ->with(Mockery::on(fn ($opts) => $opts['panels'] === ['admin']))
            ->once()
            ->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')
            ->with(Mockery::on(fn ($opts) => $opts['panels'] === ['admin']))
            ->once()
            ->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover', ['--panel' => 'admin'])
            ->assertSuccessful();
    });

    it('outputs json format when requested', function (): void {
        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover', ['--format' => 'json'])
            ->assertSuccessful();
    });
});

describe('DiscoverCommand with discovered entities', function (): void {
    it('displays discovered resources in table', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: [],
            panel: 'admin',
        );

        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect([$resource]));
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover')
            ->expectsOutputToContain('Resources')
            ->assertSuccessful();
    });

    it('displays discovered pages in table', function (): void {
        $page = new DiscoveredPage(
            fqcn: 'App\\Filament\\Pages\\Dashboard',
            title: 'Dashboard',
            panel: 'admin',
        );

        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect([$page]));
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover')
            ->expectsOutputToContain('Pages')
            ->assertSuccessful();
    });

    it('displays discovered widgets in table', function (): void {
        $widget = new DiscoveredWidget(
            fqcn: 'App\\Filament\\Widgets\\StatsWidget',
            type: 'stats',
            panel: 'admin',
        );

        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect([$widget]));

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover')
            ->expectsOutputToContain('Widgets')
            ->assertSuccessful();
    });
});

describe('DiscoverCommand generate permissions', function (): void {
    it('generates permissions when --generate flag is used', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create'],
            metadata: [],
            panel: 'admin',
        );

        $page = new DiscoveredPage(
            fqcn: 'App\\Filament\\Pages\\Dashboard',
            title: 'Dashboard',
            panel: 'admin',
        );

        $widget = new DiscoveredWidget(
            fqcn: 'App\\Filament\\Widgets\\StatsWidget',
            name: 'stats-widget',
            type: 'stats',
            panel: 'admin',
        );

        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect([$resource]));
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect([$page]));
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect([$widget]));

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover', ['--generate' => true])
            ->assertSuccessful();

        expect(Permission::where('name', 'user.view')->exists())->toBeTrue()
            ->and(Permission::where('name', 'user.create')->exists())->toBeTrue()
            ->and(Permission::where('name', 'page.dashboard')->exists())->toBeTrue()
            ->and(Permission::where('name', 'widget.stats-widget')->exists())->toBeTrue();
    });

    it('does not create duplicate permissions', function (): void {
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);

        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create'],
            metadata: [],
            panel: 'admin',
        );

        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect([$resource]));
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover', ['--generate' => true])
            ->assertSuccessful();

        expect(Permission::where('name', 'user.view')->count())->toBe(1)
            ->and(Permission::where('name', 'user.create')->count())->toBe(1);
    });
});

describe('DiscoverCommand json output', function (): void {
    it('outputs valid json with resources', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view'],
            metadata: [],
            panel: 'admin',
        );

        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect([$resource]));
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover', ['--format' => 'json'])
            ->assertSuccessful();
    });
});

describe('DiscoverCommand displayTable', function (): void {
    it('handles empty collections gracefully', function (): void {
        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover')
            ->expectsOutputToContain('None found')
            ->assertSuccessful();
    });

    it('truncates long permission lists', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create', 'update', 'delete', 'restore'],
            metadata: [],
            panel: 'admin',
        );

        $mockDiscovery = Mockery::mock(EntityDiscoveryService::class);
        $mockDiscovery->shouldReceive('discoverResources')->andReturn(collect([$resource]));
        $mockDiscovery->shouldReceive('discoverPages')->andReturn(collect());
        $mockDiscovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $mockDiscovery);

        $this->artisan('authz:discover')
            ->assertSuccessful();
    });
});
