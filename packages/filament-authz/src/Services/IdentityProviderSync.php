<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class IdentityProviderSync
{
    protected string $providerType;

    protected string $providerName;

    /**
     * @var array<string, string>
     */
    protected array $groupToRoleMapping = [];

    public function __construct()
    {
        $this->providerType = 'ldap';
        $this->providerName = 'default';
    }

    /**
     * Set the provider type.
     */
    public function setProviderType(string $type): self
    {
        $this->providerType = $type;

        return $this;
    }

    /**
     * Set the provider name.
     */
    public function setProviderName(string $name): self
    {
        $this->providerName = $name;

        return $this;
    }

    /**
     * Load mappings from database.
     */
    public function loadMappings(): self
    {
        $tablePrefix = config('filament-authz.database.table_prefix', 'authz_');
        $table = $tablePrefix . 'identity_provider_mappings';

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return $this;
        }

        $this->groupToRoleMapping = DB::table($table)
            ->where('provider_type', $this->providerType)
            ->where('provider_name', $this->providerName)
            ->where('is_active', true)
            ->pluck('local_role', 'external_group')
            ->toArray();

        return $this;
    }

    /**
     * Set mapping manually.
     *
     * @param  array<string, string>  $mapping
     */
    public function setMapping(array $mapping): self
    {
        $this->groupToRoleMapping = $mapping;

        return $this;
    }

    /**
     * Sync a user's roles based on their external groups.
     *
     * @param  array<int, string>  $externalGroups
     * @return array<string, array<int, string>>
     */
    public function syncUserRoles(object $user, array $externalGroups): array
    {
        $this->loadMappings();

        $assignedRoles = [];
        $skippedGroups = [];

        foreach ($externalGroups as $group) {
            if (isset($this->groupToRoleMapping[$group])) {
                $roleName = $this->groupToRoleMapping[$group];

                // Check if role exists
                $role = Role::where('name', $roleName)->first();

                if ($role) {
                    if (method_exists($user, 'assignRole') && method_exists($user, 'hasRole') && ! $user->hasRole($roleName)) {
                        $user->assignRole($role);
                        $assignedRoles[] = $roleName;
                    }
                } else {
                    Log::warning("Identity sync: Role '{$roleName}' not found for group '{$group}'");
                }
            } else {
                $skippedGroups[] = $group;
            }
        }

        return [
            'assigned' => $assignedRoles,
            'skipped' => $skippedGroups,
        ];
    }

    /**
     * Parse LDAP groups from user attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    public function parseLdapGroups(array $attributes): array
    {
        $memberOf = $attributes['memberof'] ?? $attributes['memberOf'] ?? [];

        if (is_string($memberOf)) {
            $memberOf = [$memberOf];
        }

        $groups = [];

        foreach ($memberOf as $dn) {
            // Extract CN from DN (e.g., "CN=Admins,OU=Groups,DC=example,DC=com" -> "Admins")
            if (preg_match('/CN=([^,]+)/i', $dn, $matches)) {
                $groups[] = $matches[1];
            }
        }

        return $groups;
    }

    /**
     * Parse SAML groups from assertion.
     *
     * @param  array<string, mixed>  $assertion
     * @return array<int, string>
     */
    public function parseSamlGroups(array $assertion): array
    {
        // Common SAML group attribute names
        $groupAttributes = [
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups',
            'groups',
            'memberOf',
            'Group',
        ];

        foreach ($groupAttributes as $attr) {
            if (isset($assertion[$attr])) {
                $groups = $assertion[$attr];

                return is_array($groups) ? $groups : [$groups];
            }
        }

        return [];
    }

    /**
     * Save a mapping to the database.
     */
    public function saveMapping(string $externalGroup, string $localRole): bool
    {
        $tablePrefix = config('filament-authz.database.table_prefix', 'authz_');
        $table = $tablePrefix . 'identity_provider_mappings';

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return false;
        }

        DB::table($table)->updateOrInsert(
            [
                'provider_type' => $this->providerType,
                'provider_name' => $this->providerName,
                'external_group' => $externalGroup,
            ],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'local_role' => $localRole,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        return true;
    }

    /**
     * Delete a mapping.
     */
    public function deleteMapping(string $externalGroup): bool
    {
        $tablePrefix = config('filament-authz.database.table_prefix', 'authz_');
        $table = $tablePrefix . 'identity_provider_mappings';

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return false;
        }

        return DB::table($table)
            ->where('provider_type', $this->providerType)
            ->where('provider_name', $this->providerName)
            ->where('external_group', $externalGroup)
            ->delete() > 0;
    }

    /**
     * Get all mappings for the current provider.
     *
     * @return Collection<int, object>
     */
    public function getAllMappings(): Collection
    {
        $tablePrefix = config('filament-authz.database.table_prefix', 'authz_');
        $table = $tablePrefix . 'identity_provider_mappings';

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return collect();
        }

        return DB::table($table)
            ->where('provider_type', $this->providerType)
            ->where('provider_name', $this->providerName)
            ->get();
    }
}
