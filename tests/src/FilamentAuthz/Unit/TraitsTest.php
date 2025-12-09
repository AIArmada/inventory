<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use AIArmada\FilamentAuthz\Concerns\HasResourceAuthz;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;

test('HasPageAuthz trait exists', function (): void {
    expect(trait_exists(HasPageAuthz::class))->toBeTrue();
});

test('HasWidgetAuthz trait exists', function (): void {
    expect(trait_exists(HasWidgetAuthz::class))->toBeTrue();
});

test('HasResourceAuthz trait exists', function (): void {
    expect(trait_exists(HasResourceAuthz::class))->toBeTrue();
});

test('HasPanelAuthz trait exists', function (): void {
    expect(trait_exists(HasPanelAuthz::class))->toBeTrue();
});

test('HasPageAuthz has required methods', function (): void {
    $methods = get_class_methods(new class {
        use HasPageAuthz;
    });

    expect($methods)
        ->toContain('shouldRegisterNavigation')
        ->toContain('canAccess')
        ->toContain('getPagePermissionKey');
});

test('HasWidgetAuthz has required methods', function (): void {
    $methods = get_class_methods(new class {
        use HasWidgetAuthz;
    });

    expect($methods)
        ->toContain('canView')
        ->toContain('getWidgetPermissionKey');
});

test('HasResourceAuthz has required methods', function (): void {
    $methods = get_class_methods(new class {
        use HasResourceAuthz;

        public static function getModel(): string
        {
            return 'test';
        }
    });

    expect($methods)
        ->toContain('getAllAbilities')
        ->toContain('getPermissionFor')
        ->toContain('canPerform');
});

test('HasPanelAuthz has required methods', function (): void {
    $methods = get_class_methods(new class {
        use HasPanelAuthz;
    });

    expect($methods)
        ->toContain('canAccessPanel')
        ->toContain('getAccessiblePanels')
        ->toContain('hasAnyPanelAccess')
        ->toContain('getDefaultPanel');
});

test('HasResourceAuthz generates permission key correctly', function (): void {
    $class = new class {
        use HasResourceAuthz;

        public static function getModel(): string
        {
            return 'App\\Models\\User';
        }
    };

    $permission = $class::getPermissionFor('view');

    expect($permission)->toBe('user.view');
});
