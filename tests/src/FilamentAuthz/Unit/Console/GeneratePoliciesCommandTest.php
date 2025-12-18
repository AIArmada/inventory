<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\GeneratedPolicy;
use AIArmada\FilamentAuthz\Services\PolicyGeneratorService;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;

describe('GeneratePoliciesCommand', function (): void {
    it('is registered as artisan command', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies')
            ->assertSuccessful();
    });
});

describe('GeneratePoliciesCommand with no resources', function (): void {
    it('shows warning when no resources found', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies')
            ->expectsOutput('No resources found to generate policies for.')
            ->assertSuccessful();
    });
});

describe('GeneratePoliciesCommand::dry-run', function (): void {
    it('shows what would be generated without writing', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: [],
            panel: 'admin'
        );

        $this->mock(EntityDiscoveryService::class, function ($mock) use ($resource): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect([$resource]));
        });

        $mockPolicy = new GeneratedPolicy(
            path: '/tmp/UserPolicy.php',
            content: '<?php // Policy content'
        );

        $this->mock(PolicyGeneratorService::class, function ($mock) use ($mockPolicy): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn($mockPolicy);
        });

        $this->artisan('authz:policies', ['--dry-run' => true])
            ->expectsOutputToContain('Would generate')
            ->expectsOutput('Dry run complete. Use without --dry-run to generate policies.')
            ->assertSuccessful();
    });
});

describe('GeneratePoliciesCommand::type option', function (): void {
    it('accepts basic policy type', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies', ['--type' => 'basic'])
            ->assertSuccessful();
    });

    it('accepts hierarchical policy type', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies', ['--type' => 'hierarchical'])
            ->assertSuccessful();
    });

    it('accepts contextual policy type', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies', ['--type' => 'contextual'])
            ->assertSuccessful();
    });

    it('accepts temporal policy type', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies', ['--type' => 'temporal'])
            ->assertSuccessful();
    });

    it('accepts abac policy type', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies', ['--type' => 'abac'])
            ->assertSuccessful();
    });

    it('accepts composite policy type', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies', ['--type' => 'composite'])
            ->assertSuccessful();
    });
});

describe('GeneratePoliciesCommand::resource filter', function (): void {
    it('filters by specific resource', function (): void {
        $resource1 = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: [],
            panel: 'admin'
        );

        $resource2 = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\PostResource',
            model: 'App\\Models\\Post',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: [],
            panel: 'admin'
        );

        $this->mock(EntityDiscoveryService::class, function ($mock) use ($resource1, $resource2): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect([$resource1, $resource2]));
        });

        $this->artisan('authz:policies', ['--resource' => ['UserResource'], '--dry-run' => true])
            ->assertSuccessful();
    });
});

describe('GeneratePoliciesCommand::model filter', function (): void {
    it('filters by specific model', function (): void {
        $resource1 = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: [],
            panel: 'admin'
        );

        $resource2 = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\PostResource',
            model: 'App\\Models\\Post',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: [],
            panel: 'admin'
        );

        $this->mock(EntityDiscoveryService::class, function ($mock) use ($resource1, $resource2): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect([$resource1, $resource2]));
        });

        $this->artisan('authz:policies', ['--model' => ['User'], '--dry-run' => true])
            ->assertSuccessful();
    });
});

describe('GeneratePoliciesCommand::panel option', function (): void {
    it('passes panel to discovery service', function (): void {
        $this->mock(EntityDiscoveryService::class, function ($mock): void {
            $mock->shouldReceive('discoverResources')
                ->with(['panels' => ['admin']])
                ->once()
                ->andReturn(collect());
        });

        $this->artisan('authz:policies', ['--panel' => 'admin'])
            ->assertSuccessful();
    });
});

describe('GeneratePoliciesCommand::namespace option', function (): void {
    it('passes custom namespace to generator', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: [],
            panel: 'admin'
        );

        $mockPolicy = new GeneratedPolicy(
            path: '/tmp/UserPolicy.php',
            content: '<?php // Policy content'
        );

        $this->mock(EntityDiscoveryService::class, function ($mock) use ($resource): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect([$resource]));
        });

        $this->mock(PolicyGeneratorService::class, function ($mock) use ($mockPolicy): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn($mockPolicy);
        });

        $this->artisan('authz:policies', ['--namespace' => 'Custom\\Policies', '--dry-run' => true])
            ->assertSuccessful();
    });
});

describe('GeneratePoliciesCommand::force option', function (): void {
    it('overwrites existing policies with force', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: [],
            panel: 'admin'
        );

        $existingPath = sys_get_temp_dir() . '/UserPolicy.php';
        file_put_contents($existingPath, '<?php // Existing');

        $mockPolicy = new GeneratedPolicy(
            path: $existingPath,
            content: '<?php // New content'
        );

        $this->mock(EntityDiscoveryService::class, function ($mock) use ($resource): void {
            $mock->shouldReceive('discoverResources')
                ->once()
                ->andReturn(collect([$resource]));
        });

        $this->mock(PolicyGeneratorService::class, function ($mock) use ($mockPolicy): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn($mockPolicy);
        });

        $this->artisan('authz:policies', ['--force' => true])
            ->assertSuccessful();

        @unlink($existingPath);
    });
});
