<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AuthzUser extends Authenticatable
{
    use HasUuids;

    protected $table = 'authz_users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
