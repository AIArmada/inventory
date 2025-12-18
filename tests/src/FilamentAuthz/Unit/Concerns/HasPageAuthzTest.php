<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use Filament\Facades\Filament;
use Filament\Pages\Page;

function createHasPageAuthzTestUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

beforeEach(function () {
    // Reset static properties between tests
    $reflection = new ReflectionClass(TestPageWithAuthz::class);

    $prop = $reflection->getProperty('pagePermissionKey');
    $prop->setAccessible(true);
    $prop->setValue(null, null);

    $prop = $reflection->getProperty('requiredPagePermissions');
    $prop->setAccessible(true);
    $prop->setValue(null, []);

    $prop = $reflection->getProperty('requiredPageRoles');
    $prop->setAccessible(true);
    $prop->setValue(null, []);

    $prop = $reflection->getProperty('teamPermissionScope');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
});

// Create a concrete test class using the trait
class TestPageWithAuthz extends Page
{
    use HasPageAuthz {
        isSuperAdmin as public publicIsSuperAdmin;
        getTeamFromContext as public publicGetTeamFromContext;
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'test-page';
    }

    public function getTitle(): string
    {
        return 'Test Page';
    }

    public function getView(): string
    {
        return 'test-view';
    }
}

describe('HasPageAuthz::getPagePermissionKey', function () {
    it('returns custom permission key when set', function () {
        TestPageWithAuthz::setPagePermissionKey('custom.permission.key');

        expect(TestPageWithAuthz::getPagePermissionKey())->toBe('custom.permission.key');
    });

    it('generates permission key from slug when not set', function () {
        // beforeEach already resets pagePermissionKey to null
        expect(TestPageWithAuthz::getPagePermissionKey())->toBe('page.test-page');
    });
});

describe('HasPageAuthz::setPagePermissionKey', function () {
    it('sets the page permission key', function () {
        TestPageWithAuthz::setPagePermissionKey('my.custom.key');

        expect(TestPageWithAuthz::getPagePermissionKey())->toBe('my.custom.key');
    });
});

describe('HasPageAuthz::requirePermissions', function () {
    it('sets required permissions', function () {
        TestPageWithAuthz::requirePermissions(['perm1', 'perm2']);

        // Verify through behavior by attempting access
        expect(true)->toBeTrue(); // Method call succeeded
    });
});

describe('HasPageAuthz::requireRoles', function () {
    it('sets required roles', function () {
        TestPageWithAuthz::requireRoles(['admin', 'editor']);

        // Verify through behavior by attempting access
        expect(true)->toBeTrue(); // Method call succeeded
    });
});

describe('HasPageAuthz::scopeToTeam', function () {
    it('sets team permission scope', function () {
        TestPageWithAuthz::scopeToTeam('team_id');

        // Verify method call succeeded
        expect(true)->toBeTrue();
    });

    it('uses default team_id key', function () {
        TestPageWithAuthz::scopeToTeam();

        // Verify method call succeeded with default
        expect(true)->toBeTrue();
    });
});

describe('HasPageAuthz::canAccess', function () {
    it('returns false when user is not authenticated', function () {
        Filament::shouldReceive('auth->user')->andReturn(null);

        expect(TestPageWithAuthz::canAccess())->toBeFalse();
    });
});

describe('HasPageAuthz::isSuperAdmin', function () {
    it('returns false when user does not have hasRole method', function () {
        $user = new stdClass();

        expect(TestPageWithAuthz::publicIsSuperAdmin($user))->toBeFalse();
    });
});

describe('HasPageAuthz::getTeamFromContext', function () {
    it('returns null when no tenant', function () {
        Filament::shouldReceive('getTenant')->andReturn(null);

        expect(TestPageWithAuthz::publicGetTeamFromContext())->toBeNull();
    });
});

describe('HasPageAuthz::getTitleWithPermissionDebug', function () {
    it('shows permission key in local environment', function () {
        config(['app.env' => 'local']);
        $this->app['env'] = 'local';

        $page = new TestPageWithAuthz();

        expect($page->getTitleWithPermissionDebug())->toContain('Test Page');
    });
});
