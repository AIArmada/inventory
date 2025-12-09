<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Enums;

enum SetupStage: string
{
    case Welcome = 'welcome';
    case Detection = 'detection';
    case Configuration = 'configuration';
    case Database = 'database';
    case Roles = 'roles';
    case Permissions = 'permissions';
    case Policies = 'policies';
    case UserSetup = 'user_setup';
    case Verification = 'verification';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome',
            self::Detection => 'Environment Detection',
            self::Configuration => 'Configuration',
            self::Database => 'Database Setup',
            self::Roles => 'Role Setup',
            self::Permissions => 'Permission Generation',
            self::Policies => 'Policy Generation',
            self::UserSetup => 'Super Admin Assignment',
            self::Verification => 'Verification',
            self::Complete => 'Complete',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Welcome => '👋',
            self::Detection => '🔍',
            self::Configuration => '⚙️',
            self::Database => '📦',
            self::Roles => '👥',
            self::Permissions => '🔑',
            self::Policies => '📋',
            self::UserSetup => '👑',
            self::Verification => '✔️',
            self::Complete => '✅',
        };
    }
}
