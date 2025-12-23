<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cashier\Fixtures;

use AIArmada\Cashier\Billable;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class OwnerScopedUser extends Authenticatable
{
    use Billable;
    use HasOwner;
    use HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'cashier.tests.owner';

    protected static bool $ownerScopeEnabledByDefault = true;

    protected $guarded = [];

    protected $table = 'users';

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    public function customerName(): ?string
    {
        return $this->name;
    }

    public function customerEmail(): ?string
    {
        return $this->email;
    }

    public function customerPhone(): ?string
    {
        return $this->phone ?? null;
    }
}
