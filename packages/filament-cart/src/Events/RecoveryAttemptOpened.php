<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Events;

use AIArmada\FilamentCart\Models\RecoveryAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecoveryAttemptOpened
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RecoveryAttempt $attempt,
    ) {}
}
