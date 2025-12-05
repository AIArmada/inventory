<?php

declare(strict_types=1);

namespace AIArmada\Chip\Events;

use AIArmada\Chip\Models\RecurringSchedule;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a recurring charge fails and a retry is scheduled.
 */
final class RecurringChargeRetryScheduled
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly RecurringSchedule $schedule,
        public readonly int $retryDelayHours,
    ) {}
}
