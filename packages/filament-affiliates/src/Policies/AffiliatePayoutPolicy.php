<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Contracts\Auth\Access\Authorizable;

class AffiliatePayoutPolicy
{
    public function update(Authorizable $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.update');
    }

    public function export(Authorizable $user, AffiliatePayout $payout): bool
    {
        return $user->can('affiliates.payout.export');
    }
}
