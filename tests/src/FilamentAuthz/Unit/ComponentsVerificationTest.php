<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\HasOwnerPermissions;
use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use AIArmada\FilamentAuthz\Concerns\HasResourceAuthz;
use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentAuthz\FilamentAuthzServiceProvider;
use AIArmada\FilamentAuthz\Http\Middleware\AuthorizePanelRoles;
use AIArmada\FilamentAuthz\Listeners\PermissionEventSubscriber;
use AIArmada\FilamentAuthz\Pages\AuditLogPage;
use AIArmada\FilamentAuthz\Pages\AuthzDashboardPage;
use AIArmada\FilamentAuthz\Pages\PermissionExplorer;
use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use AIArmada\FilamentAuthz\Pages\PolicyDesignerPage;
use AIArmada\FilamentAuthz\Pages\RoleHierarchyPage;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use AIArmada\FilamentAuthz\Support\Macros\ActionMacros;
use AIArmada\FilamentAuthz\Support\Macros\ColumnMacros;
use AIArmada\FilamentAuthz\Support\Macros\FilterMacros;
use AIArmada\FilamentAuthz\Support\Macros\FormMacros;
use AIArmada\FilamentAuthz\Support\Macros\NavigationItemMacros;
use AIArmada\FilamentAuthz\Support\Macros\NavigationMacros;
use AIArmada\FilamentAuthz\Support\Macros\TableComponentMacros;
use AIArmada\FilamentAuthz\Support\ResourcePermissionDiscovery;
use AIArmada\FilamentAuthz\Widgets\RoleHierarchyWidget;

describe('Concerns Traits', function (): void {
    describe('HasOwnerPermissions', function (): void {
        it('is a trait', function (): void {
            $reflection = new ReflectionClass(HasOwnerPermissions::class);

            expect($reflection->isTrait())->toBeTrue();
        });

        it('has canUserPerform method', function (): void {
            $reflection = new ReflectionClass(HasOwnerPermissions::class);

            expect($reflection->hasMethod('canUserPerform'))->toBeTrue();
        });

        it('has isOwnedBy method', function (): void {
            $reflection = new ReflectionClass(HasOwnerPermissions::class);

            expect($reflection->hasMethod('isOwnedBy'))->toBeTrue();
        });

        it('has getPermissionName method', function (): void {
            $reflection = new ReflectionClass(HasOwnerPermissions::class);

            expect($reflection->hasMethod('getPermissionName'))->toBeTrue();
        });
    });

    describe('HasPageAuthz', function (): void {
        it('is a trait', function (): void {
            $reflection = new ReflectionClass(HasPageAuthz::class);

            expect($reflection->isTrait())->toBeTrue();
        });

        it('has canAccess method', function (): void {
            $reflection = new ReflectionClass(HasPageAuthz::class);

            expect($reflection->hasMethod('canAccess'))->toBeTrue();
        });

        it('has getPagePermissionKey method', function (): void {
            $reflection = new ReflectionClass(HasPageAuthz::class);

            expect($reflection->hasMethod('getPagePermissionKey'))->toBeTrue();
        });
    });

    describe('HasResourceAuthz', function (): void {
        it('is a trait', function (): void {
            $reflection = new ReflectionClass(HasResourceAuthz::class);

            expect($reflection->isTrait())->toBeTrue();
        });

        it('has abilities method', function (): void {
            $reflection = new ReflectionClass(HasResourceAuthz::class);

            expect($reflection->hasMethod('abilities'))->toBeTrue();
        });

        it('has customAbilities property', function (): void {
            $reflection = new ReflectionClass(HasResourceAuthz::class);

            expect($reflection->hasProperty('customAbilities'))->toBeTrue();
        });

        it('has permissionPrefix property', function (): void {
            $reflection = new ReflectionClass(HasResourceAuthz::class);

            expect($reflection->hasProperty('permissionPrefix'))->toBeTrue();
        });
    });

    describe('HasWidgetAuthz', function (): void {
        it('is a trait', function (): void {
            $reflection = new ReflectionClass(HasWidgetAuthz::class);

            expect($reflection->isTrait())->toBeTrue();
        });

        it('has canView method', function (): void {
            $reflection = new ReflectionClass(HasWidgetAuthz::class);

            expect($reflection->hasMethod('canView'))->toBeTrue();
        });
    });
});

describe('Pages', function (): void {
    describe('AuditLogPage', function (): void {
        it('can be instantiated', function (): void {
            $page = new AuditLogPage;

            expect($page)->toBeInstanceOf(AuditLogPage::class);
        });
    });

    describe('AuthzDashboardPage', function (): void {
        it('can be instantiated', function (): void {
            $page = new AuthzDashboardPage;

            expect($page)->toBeInstanceOf(AuthzDashboardPage::class);
        });

        it('has getHeaderWidgets method', function (): void {
            expect(method_exists(AuthzDashboardPage::class, 'getHeaderWidgets'))->toBeTrue();
        });
    });

    describe('PermissionExplorer', function (): void {
        it('can be instantiated', function (): void {
            $page = new PermissionExplorer;

            expect($page)->toBeInstanceOf(PermissionExplorer::class);
        });

        it('has correct navigation label', function (): void {
            $reflection = new ReflectionClass(PermissionExplorer::class);

            expect($reflection->hasMethod('getNavigationLabel'))->toBeTrue();
        });
    });

    describe('PermissionMatrixPage', function (): void {
        it('can be instantiated', function (): void {
            $page = new PermissionMatrixPage;

            expect($page)->toBeInstanceOf(PermissionMatrixPage::class);
        });
    });

    describe('PolicyDesignerPage', function (): void {
        it('can be instantiated', function (): void {
            $page = new PolicyDesignerPage;

            expect($page)->toBeInstanceOf(PolicyDesignerPage::class);
        });
    });

    describe('RoleHierarchyPage', function (): void {
        it('can be instantiated', function (): void {
            $page = new RoleHierarchyPage;

            expect($page)->toBeInstanceOf(RoleHierarchyPage::class);
        });
    });
});

describe('Plugin and Provider', function (): void {
    describe('FilamentAuthzPlugin', function (): void {
        it('can be created', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
        });

        it('has getId method', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            expect($plugin->getId())->toBe('aiarmada-filament-authz');
        });

        it('has boot method', function (): void {
            expect(method_exists(FilamentAuthzPlugin::class, 'boot'))->toBeTrue();
        });

        it('has register method', function (): void {
            expect(method_exists(FilamentAuthzPlugin::class, 'register'))->toBeTrue();
        });

        it('is fluent interface for discoverPermissions', function (): void {
            $plugin = FilamentAuthzPlugin::make()
                ->discoverPermissions(true);

            expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
        });

        it('is fluent interface for discoverPermissionsFrom', function (): void {
            $plugin = FilamentAuthzPlugin::make()
                ->discoverPermissionsFrom(['App\\Models']);

            expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
        });
    });

    describe('FilamentAuthzServiceProvider', function (): void {
        it('has register method', function (): void {
            expect(method_exists(FilamentAuthzServiceProvider::class, 'register'))->toBeTrue();
        });

        it('has boot method', function (): void {
            expect(method_exists(FilamentAuthzServiceProvider::class, 'boot'))->toBeTrue();
        });
    });
});

describe('Middleware', function (): void {
    describe('AuthorizePanelRoles', function (): void {
        it('can be instantiated', function (): void {
            $middleware = new AuthorizePanelRoles;

            expect($middleware)->toBeInstanceOf(AuthorizePanelRoles::class);
        });

        it('has handle method', function (): void {
            expect(method_exists(AuthorizePanelRoles::class, 'handle'))->toBeTrue();
        });
    });
});

describe('Listeners', function (): void {
    describe('PermissionEventSubscriber', function (): void {
        it('can be instantiated', function (): void {
            $auditLogger = Mockery::mock(AuditLogger::class);
            $subscriber = new PermissionEventSubscriber($auditLogger);

            expect($subscriber)->toBeInstanceOf(PermissionEventSubscriber::class);
        });

        it('has subscribe method', function (): void {
            expect(method_exists(PermissionEventSubscriber::class, 'subscribe'))->toBeTrue();
        });
    });
});

describe('Widgets', function (): void {
    describe('RoleHierarchyWidget', function (): void {
        it('can be instantiated', function (): void {
            $widget = new RoleHierarchyWidget;

            expect($widget)->toBeInstanceOf(RoleHierarchyWidget::class);
        });

        it('has getHierarchy method', function (): void {
            expect(method_exists(RoleHierarchyWidget::class, 'getHierarchy'))->toBeTrue();
        });
    });
});

describe('Macros', function (): void {
    describe('ActionMacros', function (): void {
        it('has register method', function (): void {
            expect(method_exists(ActionMacros::class, 'register'))->toBeTrue();
        });
    });

    describe('ColumnMacros', function (): void {
        it('has register method', function (): void {
            expect(method_exists(ColumnMacros::class, 'register'))->toBeTrue();
        });
    });

    describe('FilterMacros', function (): void {
        it('has register method', function (): void {
            expect(method_exists(FilterMacros::class, 'register'))->toBeTrue();
        });
    });

    describe('FormMacros', function (): void {
        it('has register method', function (): void {
            expect(method_exists(FormMacros::class, 'register'))->toBeTrue();
        });
    });

    describe('NavigationItemMacros', function (): void {
        it('has register method', function (): void {
            expect(method_exists(NavigationItemMacros::class, 'register'))->toBeTrue();
        });
    });

    describe('NavigationMacros', function (): void {
        it('has register method', function (): void {
            expect(method_exists(NavigationMacros::class, 'register'))->toBeTrue();
        });
    });

    describe('TableComponentMacros', function (): void {
        it('has register method', function (): void {
            expect(method_exists(TableComponentMacros::class, 'register'))->toBeTrue();
        });
    });
});

describe('Support', function (): void {
    describe('ResourcePermissionDiscovery', function (): void {
        it('can be instantiated with PermissionRegistry', function (): void {
            $registry = app(PermissionRegistry::class);
            $discovery = new ResourcePermissionDiscovery($registry);

            expect($discovery)->toBeInstanceOf(ResourcePermissionDiscovery::class);
        });

        it('has discoverFromPanel method', function (): void {
            expect(method_exists(ResourcePermissionDiscovery::class, 'discoverFromPanel'))->toBeTrue();
        });

        it('has discoverFromNamespaces method', function (): void {
            expect(method_exists(ResourcePermissionDiscovery::class, 'discoverFromNamespaces'))->toBeTrue();
        });
    });
});

describe('Services', function (): void {
    describe('RoleTemplateService', function (): void {
        it('can be instantiated', function (): void {
            $service = new RoleTemplateService;

            expect($service)->toBeInstanceOf(RoleTemplateService::class);
        });

        it('has createTemplate method', function (): void {
            expect(method_exists(RoleTemplateService::class, 'createTemplate'))->toBeTrue();
        });

        it('has findBySlug method', function (): void {
            expect(method_exists(RoleTemplateService::class, 'findBySlug'))->toBeTrue();
        });

        it('returns null for non-existent slug', function (): void {
            $service = new RoleTemplateService;
            $result = $service->findBySlug('non-existent-slug');

            expect($result)->toBeNull();
        });

        it('has getActiveTemplates method', function (): void {
            expect(method_exists(RoleTemplateService::class, 'getActiveTemplates'))->toBeTrue();

            $service = new RoleTemplateService;
            $result = $service->getActiveTemplates();

            expect($result)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
        });

        it('has createRoleFromTemplate method', function (): void {
            expect(method_exists(RoleTemplateService::class, 'createRoleFromTemplate'))->toBeTrue();
        });

        it('has syncRoleWithTemplate method', function (): void {
            expect(method_exists(RoleTemplateService::class, 'syncRoleWithTemplate'))->toBeTrue();
        });

        it('has syncAllRolesFromTemplate method', function (): void {
            expect(method_exists(RoleTemplateService::class, 'syncAllRolesFromTemplate'))->toBeTrue();
        });

        it('has getRolesFromTemplate method', function (): void {
            expect(method_exists(RoleTemplateService::class, 'getRolesFromTemplate'))->toBeTrue();
        });

        it('has deleteTemplate method', function (): void {
            expect(method_exists(RoleTemplateService::class, 'deleteTemplate'))->toBeTrue();
        });
    });

    describe('AuditLogger', function (): void {
        it('can be instantiated', function (): void {
            $logger = new AuditLogger;

            expect($logger)->toBeInstanceOf(AuditLogger::class);
        });

        it('has log method', function (): void {
            expect(method_exists(AuditLogger::class, 'log'))->toBeTrue();
        });

        it('has logPermissionGranted method', function (): void {
            expect(method_exists(AuditLogger::class, 'logPermissionGranted'))->toBeTrue();
        });

        it('has logPermissionRevoked method', function (): void {
            expect(method_exists(AuditLogger::class, 'logPermissionRevoked'))->toBeTrue();
        });

        it('has logRoleAssigned method', function (): void {
            expect(method_exists(AuditLogger::class, 'logRoleAssigned'))->toBeTrue();
        });

        it('has logRoleRemoved method', function (): void {
            expect(method_exists(AuditLogger::class, 'logRoleRemoved'))->toBeTrue();
        });

        it('has logAccessDenied method', function (): void {
            expect(method_exists(AuditLogger::class, 'logAccessDenied'))->toBeTrue();
        });
    });
});
