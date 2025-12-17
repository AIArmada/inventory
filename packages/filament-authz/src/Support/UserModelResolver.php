<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as FoundationUser;

final class UserModelResolver
{
    /**
     * @return class-string<Model>
     */
    public static function resolve(): string
    {
        $configured = config('filament-authz.user_model');
        if (is_string($configured) && class_exists($configured)) {
            /** @var class-string<Model> $configured */
            return $configured;
        }

        $authProviderModel = config('auth.providers.users.model');
        if (is_string($authProviderModel) && class_exists($authProviderModel)) {
            /** @var class-string<Model> $authProviderModel */
            return $authProviderModel;
        }

        return FoundationUser::class;
    }
}
