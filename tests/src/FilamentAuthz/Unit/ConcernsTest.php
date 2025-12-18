<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\AccessesRoleHierarchy;
use AIArmada\FilamentAuthz\Concerns\HasAutoPermissions;
use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use Spatie\Permission\Models\Role;

describe('AccessesRoleHierarchy trait', function (): void {
    it('can get and set role parent id', function (): void {
        $class = new class
        {
            use AccessesRoleHierarchy;

            public function testGetParentId(Role $role): ?string
            {
                return $this->getRoleParentId($role);
            }

            public function testSetParentId(Role $role, ?string $parentId): void
            {
                $this->setRoleParentId($role, $parentId);
            }
        };

        $role = new Role;
        $role->setAttribute('parent_role_id', 'test-uuid');

        expect($class->testGetParentId($role))->toBe('test-uuid');

        $class->testSetParentId($role, 'new-uuid');
        expect($role->getAttribute('parent_role_id'))->toBe('new-uuid');

        $class->testSetParentId($role, null);
        expect($role->getAttribute('parent_role_id'))->toBeNull();
    });

    it('can get and set role level', function (): void {
        $class = new class
        {
            use AccessesRoleHierarchy;

            public function testGetLevel(Role $role): int
            {
                return $this->getRoleLevel($role);
            }

            public function testSetLevel(Role $role, int $level): void
            {
                $this->setRoleLevel($role, $level);
            }
        };

        $role = new Role;

        expect($class->testGetLevel($role))->toBe(0);

        $role->setAttribute('level', 5);
        expect($class->testGetLevel($role))->toBe(5);

        $class->testSetLevel($role, 10);
        expect($role->getAttribute('level'))->toBe(10);
    });

    it('can check if role is system role', function (): void {
        $class = new class
        {
            use AccessesRoleHierarchy;

            public function testIsSystem(Role $role): bool
            {
                return $this->isSystemRole($role);
            }
        };

        $role = new Role;

        expect($class->testIsSystem($role))->toBeFalse();

        $role->setAttribute('is_system', true);
        expect($class->testIsSystem($role))->toBeTrue();
    });

    it('can get and set role template id', function (): void {
        $class = new class
        {
            use AccessesRoleHierarchy;

            public function testGetTemplateId(Role $role): ?string
            {
                return $this->getRoleTemplateId($role);
            }

            public function testSetTemplateId(Role $role, ?string $templateId): void
            {
                $this->setRoleTemplateId($role, $templateId);
            }
        };

        $role = new Role;

        expect($class->testGetTemplateId($role))->toBeNull();

        $class->testSetTemplateId($role, 'template-123');
        expect($role->getAttribute('template_id'))->toBe('template-123');
    });

    it('can get role metadata', function (): void {
        $class = new class
        {
            use AccessesRoleHierarchy;

            public function testGetMetadata(Role $role): ?array
            {
                return $this->getRoleMetadata($role);
            }
        };

        $role = new Role;

        expect($class->testGetMetadata($role))->toBeNull();

        $role->setAttribute('metadata', ['key' => 'value']);
        expect($class->testGetMetadata($role))->toBe(['key' => 'value']);
    });

    it('can check if role is assignable', function (): void {
        $class = new class
        {
            use AccessesRoleHierarchy;

            public function testIsAssignable(Role $role): bool
            {
                return $this->isRoleAssignable($role);
            }
        };

        $role = new Role;

        expect($class->testIsAssignable($role))->toBeTrue();

        $role->setAttribute('is_assignable', false);
        expect($class->testIsAssignable($role))->toBeFalse();
    });

    it('can get role description', function (): void {
        $class = new class
        {
            use AccessesRoleHierarchy;

            public function testGetDescription(Role $role): ?string
            {
                return $this->getRoleDescription($role);
            }
        };

        $role = new Role;

        expect($class->testGetDescription($role))->toBeNull();

        $role->setAttribute('description', 'Test description');
        expect($class->testGetDescription($role))->toBe('Test description');
    });
});

describe('HasAutoPermissions trait', function (): void {
    it('can get permission key from model', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        expect($class::getPermissionKey())->toBe('product');
    });

    it('uses custom permission key if set', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            protected static ?string $permissionKey = 'custom_key';

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        expect($class::getPermissionKey())->toBe('custom_key');
    });

    it('returns default permission abilities', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        $abilities = $class::getPermissionAbilities();

        expect($abilities)->toContain('viewAny')
            ->toContain('view')
            ->toContain('create')
            ->toContain('update')
            ->toContain('delete');
    });

    it('uses custom permission abilities if set', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            /** @var array<string> */
            protected static array $permissionAbilities = ['read', 'write'];

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        expect($class::getPermissionAbilities())->toBe(['read', 'write']);
    });

    it('returns permission group from navigation group', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }

            public static function getNavigationGroup(): ?string
            {
                return 'Catalog';
            }
        };

        expect($class::getPermissionGroup())->toBe('Catalog');
    });

    it('uses custom permission group if set', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            protected static ?string $permissionGroup = 'CustomGroup';

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        expect($class::getPermissionGroup())->toBe('CustomGroup');
    });

    it('can check if wildcard should be registered', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        expect($class::shouldRegisterWildcard())->toBeTrue();
    });

    it('can disable wildcard registration', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            protected static bool $registerWildcardPermission = false;

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        expect($class::shouldRegisterWildcard())->toBeFalse();
    });

    it('generates full permission names', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            /** @var array<string> */
            protected static array $permissionAbilities = ['view', 'create'];

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        $permissions = $class::getPermissionNames();

        expect($permissions)->toContain('product.view')
            ->toContain('product.create')
            ->toContain('product.*');
    });

    it('returns false for canPerform when user is null', function (): void {
        $class = new class
        {
            use HasAutoPermissions;

            public static function getModel(): string
            {
                return 'App\\Models\\Product';
            }
        };

        expect($class::canPerform('view'))->toBeFalse();
    });
});

describe('HasWidgetAuthz trait', function (): void {
    it('generates widget permission key from class name', function (): void {
        $class = new class
        {
            use HasWidgetAuthz;
        };

        $key = $class::getWidgetPermissionKey();

        expect($key)->toStartWith('widget.');
    });

    it('can set custom widget permission key', function (): void {
        $class = new class
        {
            use HasWidgetAuthz;
        };

        $class::setWidgetPermissionKey('widget.custom_widget');

        expect($class::getWidgetPermissionKey())->toBe('widget.custom_widget');
    });

    it('can require widget permissions', function (): void {
        $class = new class
        {
            use HasWidgetAuthz;

            public static function getRequiredPermissions(): array
            {
                return self::$requiredWidgetPermissions;
            }
        };

        $class::requireWidgetPermissions(['view.stats', 'view.charts']);

        expect($class::getRequiredPermissions())->toBe(['view.stats', 'view.charts']);
    });

    it('can require widget roles', function (): void {
        $class = new class
        {
            use HasWidgetAuthz;

            public static function getRequiredRoles(): array
            {
                return self::$requiredWidgetRoles;
            }
        };

        $class::requireWidgetRoles(['admin', 'manager']);

        expect($class::getRequiredRoles())->toBe(['admin', 'manager']);
    });

    it('can scope widget to team', function (): void {
        $class = new class
        {
            use HasWidgetAuthz;

            public static function getTeamScope(): ?string
            {
                return self::$widgetTeamScope;
            }
        };

        $class::scopeWidgetToTeam('custom_team_id');

        expect($class::getTeamScope())->toBe('custom_team_id');
    });

    it('can show placeholder when unauthorized', function (): void {
        $class = new class
        {
            use HasWidgetAuthz;

            public static function getHideWhenUnauthorized(): bool
            {
                return self::$hideWhenUnauthorized;
            }
        };

        expect($class::getHideWhenUnauthorized())->toBeTrue();

        $class::showPlaceholderWhenUnauthorized();

        expect($class::getHideWhenUnauthorized())->toBeFalse();
    });
});

describe('HasPageAuthz trait', function (): void {
    it('generates page permission key', function (): void {
        $class = new class
        {
            use HasPageAuthz;

            public static function getSlug(): string
            {
                return 'settings';
            }
        };

        expect($class::getPagePermissionKey())->toBe('page.settings');
    });

    it('can set custom page permission key', function (): void {
        $class = new class
        {
            use HasPageAuthz;
        };

        $class::setPagePermissionKey('page.custom_page');

        expect($class::getPagePermissionKey())->toBe('page.custom_page');
    });

    it('can require specific permissions', function (): void {
        $class = new class
        {
            use HasPageAuthz;

            public static function getRequiredPermissions(): array
            {
                return self::$requiredPagePermissions;
            }
        };

        $class::requirePermissions(['manage.settings', 'view.system']);

        expect($class::getRequiredPermissions())->toBe(['manage.settings', 'view.system']);
    });

    it('can require specific roles', function (): void {
        $class = new class
        {
            use HasPageAuthz;

            public static function getRequiredRoles(): array
            {
                return self::$requiredPageRoles;
            }
        };

        $class::requireRoles(['admin', 'super_admin']);

        expect($class::getRequiredRoles())->toBe(['admin', 'super_admin']);
    });

    it('can scope to team', function (): void {
        $class = new class
        {
            use HasPageAuthz;

            public static function getTeamScope(): ?string
            {
                return self::$teamPermissionScope;
            }
        };

        $class::scopeToTeam('organization_id');

        expect($class::getTeamScope())->toBe('organization_id');
    });
});

describe('HasPanelAuthz trait', function (): void {
    it('has required methods', function (): void {
        $class = new class
        {
            use HasPanelAuthz;
        };

        expect(method_exists($class, 'canAccessPanel'))->toBeTrue();
        expect(method_exists($class, 'getAccessiblePanels'))->toBeTrue();
        expect(method_exists($class, 'hasAnyPanelAccess'))->toBeTrue();
        expect(method_exists($class, 'getDefaultPanel'))->toBeTrue();
    });
});
