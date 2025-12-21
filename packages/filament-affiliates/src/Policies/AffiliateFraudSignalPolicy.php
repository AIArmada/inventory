<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Illuminate\Contracts\Auth\Access\Authorizable;

class AffiliateFraudSignalPolicy
{
    public function update(Authorizable $user, AffiliateFraudSignal $signal): bool
    {
        return $user->can('affiliates.fraud.update');
    }
}
