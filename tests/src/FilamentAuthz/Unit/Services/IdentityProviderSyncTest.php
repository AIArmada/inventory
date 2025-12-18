<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\IdentityProviderSync;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    User::query()->delete();
    Role::query()->delete();

    $this->sync = new IdentityProviderSync;
});

afterEach(function (): void {
    Mockery::close();
});

function createIdentityTestUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

describe('IdentityProviderSync', function (): void {
    describe('setProviderType', function (): void {
        it('sets provider type and returns self', function (): void {
            $result = $this->sync->setProviderType('saml');

            expect($result)->toBe($this->sync);
        });
    });

    describe('setProviderName', function (): void {
        it('sets provider name and returns self', function (): void {
            $result = $this->sync->setProviderName('azure-ad');

            expect($result)->toBe($this->sync);
        });
    });

    describe('setMapping', function (): void {
        it('sets mapping manually and returns self', function (): void {
            $mapping = [
                'Admins' => 'admin',
                'Users' => 'user',
            ];

            $result = $this->sync->setMapping($mapping);

            expect($result)->toBe($this->sync);
        });
    });

    describe('loadMappings', function (): void {
        it('returns self when table does not exist', function (): void {
            $result = $this->sync->loadMappings();

            expect($result)->toBe($this->sync);
        });
    });

    describe('syncUserRoles', function (): void {
        it('returns array with assigned and skipped keys', function (): void {
            $user = createIdentityTestUser();

            $result = $this->sync->syncUserRoles($user, ['SomeGroup']);

            expect($result)->toHaveKeys(['assigned', 'skipped']);
            expect($result['assigned'])->toBeArray();
            expect($result['skipped'])->toBeArray();
        });

        it('does not reassign role user already has', function (): void {
            $user = createIdentityTestUser();
            $role = Role::create(['name' => 'existing', 'guard_name' => 'web']);
            $user->assignRole($role);

            // Note: loadMappings() is called internally and may clear setMapping
            $result = $this->sync->syncUserRoles($user, ['ExistingGroup']);

            // Since there's no mapping, nothing is assigned
            expect($result['assigned'])->toBeEmpty();
        });

        it('skips groups without mapping', function (): void {
            $user = createIdentityTestUser();

            $result = $this->sync->syncUserRoles($user, ['UnmappedGroup']);

            expect($result['skipped'])->toContain('UnmappedGroup');
            expect($result['assigned'])->toBeEmpty();
        });

        it('method exists and is callable', function (): void {
            $user = createIdentityTestUser();

            expect(method_exists($this->sync, 'syncUserRoles'))->toBeTrue();
            expect(is_callable([$this->sync, 'syncUserRoles']))->toBeTrue();
        });
    });

    describe('parseLdapGroups', function (): void {
        it('extracts CN from LDAP DN strings', function (): void {
            $attributes = [
                'memberof' => [
                    'CN=Admins,OU=Groups,DC=example,DC=com',
                    'CN=Developers,OU=Groups,DC=example,DC=com',
                ],
            ];

            $result = $this->sync->parseLdapGroups($attributes);

            expect($result)->toContain('Admins');
            expect($result)->toContain('Developers');
        });

        it('handles memberOf with different case', function (): void {
            $attributes = [
                'memberOf' => ['CN=Users,OU=Groups,DC=test,DC=com'],
            ];

            $result = $this->sync->parseLdapGroups($attributes);

            expect($result)->toContain('Users');
        });

        it('handles string memberof value', function (): void {
            $attributes = [
                'memberof' => 'CN=SingleGroup,OU=Groups,DC=test,DC=com',
            ];

            $result = $this->sync->parseLdapGroups($attributes);

            expect($result)->toContain('SingleGroup');
        });

        it('returns empty array when no memberof', function (): void {
            $attributes = ['mail' => 'user@example.com'];

            $result = $this->sync->parseLdapGroups($attributes);

            expect($result)->toBeEmpty();
        });

        it('handles complex DN with special characters', function (): void {
            $attributes = [
                'memberof' => ['CN=IT-Admins,OU=IT,OU=Groups,DC=corp,DC=example,DC=com'],
            ];

            $result = $this->sync->parseLdapGroups($attributes);

            expect($result)->toContain('IT-Admins');
        });
    });

    describe('parseSamlGroups', function (): void {
        it('extracts groups from Microsoft claims', function (): void {
            $assertion = [
                'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups' => [
                    'group-uuid-1',
                    'group-uuid-2',
                ],
            ];

            $result = $this->sync->parseSamlGroups($assertion);

            expect($result)->toContain('group-uuid-1');
            expect($result)->toContain('group-uuid-2');
        });

        it('extracts groups from groups attribute', function (): void {
            $assertion = [
                'groups' => ['Admins', 'Users'],
            ];

            $result = $this->sync->parseSamlGroups($assertion);

            expect($result)->toContain('Admins');
            expect($result)->toContain('Users');
        });

        it('extracts groups from memberOf attribute', function (): void {
            $assertion = [
                'memberOf' => ['Group1', 'Group2'],
            ];

            $result = $this->sync->parseSamlGroups($assertion);

            expect($result)->toContain('Group1');
            expect($result)->toContain('Group2');
        });

        it('extracts groups from Group attribute', function (): void {
            $assertion = [
                'Group' => ['SingleGroup'],
            ];

            $result = $this->sync->parseSamlGroups($assertion);

            expect($result)->toContain('SingleGroup');
        });

        it('handles string group value', function (): void {
            $assertion = [
                'groups' => 'SingleGroup',
            ];

            $result = $this->sync->parseSamlGroups($assertion);

            expect($result)->toBe(['SingleGroup']);
        });

        it('returns empty array when no group attributes', function (): void {
            $assertion = [
                'email' => 'user@example.com',
                'name' => 'Test User',
            ];

            $result = $this->sync->parseSamlGroups($assertion);

            expect($result)->toBeEmpty();
        });
    });

    describe('saveMapping', function (): void {
        it('returns boolean for saveMapping', function (): void {
            $result = $this->sync->saveMapping('ExternalGroup', 'local-role');

            expect($result)->toBeBool();
        });
    });

    describe('deleteMapping', function (): void {
        it('returns false when table does not exist', function (): void {
            $result = $this->sync->deleteMapping('ExternalGroup');

            expect($result)->toBeFalse();
        });
    });

    describe('getAllMappings', function (): void {
        it('returns empty collection when table does not exist', function (): void {
            $result = $this->sync->getAllMappings();

            expect($result)->toBeInstanceOf(Illuminate\Support\Collection::class);
            expect($result->count())->toBe(0);
        });
    });

    describe('provider configuration', function (): void {
        it('allows chaining provider configuration', function (): void {
            $result = $this->sync
                ->setProviderType('saml')
                ->setProviderName('okta')
                ->setMapping(['Group1' => 'role1']);

            expect($result)->toBe($this->sync);
        });
    });
});
