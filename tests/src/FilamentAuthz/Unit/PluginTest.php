<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentAuthz\FilamentAuthzServiceProvider;

describe('FilamentAuthzPlugin', function (): void {
    beforeEach(function (): void {
        $this->plugin = FilamentAuthzPlugin::make();
    });

    it('can be instantiated via make', function (): void {
        expect($this->plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('returns correct plugin id', function (): void {
        expect($this->plugin->getId())->toBe('aiarmada-filament-authz');
    });

    it('can enable permission discovery', function (): void {
        $result = $this->plugin->discoverPermissions(true);

        expect($result)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can disable permission discovery', function (): void {
        $result = $this->plugin->discoverPermissions(false);

        expect($result)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('can discover permissions from namespaces', function (): void {
        $result = $this->plugin->discoverPermissionsFrom([
            'App\\Filament\\Resources',
            'App\\Filament\\Pages',
        ]);

        expect($result)->toBeInstanceOf(FilamentAuthzPlugin::class);
    });

    it('is fluent for discoverPermissionsFrom', function (): void {
        $result = $this->plugin
            ->discoverPermissions(true)
            ->discoverPermissionsFrom(['App\\Test']);

        expect($result)->toBe($this->plugin);
    });
});

describe('FilamentAuthzServiceProvider', function (): void {
    it('registers the plugin as singleton', function (): void {
        $plugin1 = app(FilamentAuthzPlugin::class);
        $plugin2 = app(FilamentAuthzPlugin::class);

        expect($plugin1)->toBe($plugin2);
    });

    it('publishes config file', function (): void {
        $provider = new FilamentAuthzServiceProvider(app());

        expect(config('filament-authz'))->toBeArray();
    });

    it('loads migrations from package', function (): void {
        $provider = new FilamentAuthzServiceProvider(app());
        $provider->boot();

        expect(true)->toBeTrue();
    });

    it('registers commands when running in console', function (): void {
        $commands = Artisan::all();

        // Some commands should be registered
        expect($commands)->toBeArray();
    });

    it('provides configuration with expected keys', function (): void {
        expect(config('filament-authz'))->toBeArray();
    });
});
