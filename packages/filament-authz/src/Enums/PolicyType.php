<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Enums;

enum PolicyType: string
{
    case Basic = 'basic';
    case Hierarchical = 'hierarchical';
    case Temporal = 'temporal';
    case Contextual = 'contextual';
    case Abac = 'abac';
    case Composite = 'composite';

    /**
     * Get methods that don't require a model instance (single parameter).
     *
     * @return array<string>
     */
    public static function singleParamMethods(): array
    {
        return [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ];
    }

    /**
     * Get methods that use owner-aware checks.
     *
     * @return array<string>
     */
    public static function ownerAwareMethods(): array
    {
        return [
            'view',
            'update',
            'delete',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic — Simple permission checks (Shield compatible)',
            self::Hierarchical => 'Hierarchical — Uses permission group inheritance',
            self::Contextual => 'Contextual — Team/tenant/owner aware',
            self::Temporal => 'Temporal — Includes time-based permission checks',
            self::Abac => 'ABAC — Full attribute-based evaluation',
            self::Composite => 'Composite — All features combined (recommended)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Basic => 'Simple $user->can() permission checks, compatible with Filament Shield',
            self::Hierarchical => 'Uses permission group hierarchy for inherited permissions',
            self::Contextual => 'Includes team, tenant, and owner-aware permission checks',
            self::Temporal => 'Supports time-based temporary permission grants',
            self::Abac => 'Full ABAC policy engine evaluation with context attributes',
            self::Composite => 'Combines all policy types for maximum flexibility',
        };
    }
}
