<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Trait for safely dispatching cart events.
 *
 * This trait provides transactional-aware event dispatching. When a database
 * transaction is active, events are queued and dispatched only after the
 * transaction commits successfully. This prevents event listeners from seeing
 * inconsistent state if a transaction is rolled back.
 */
trait DispatchesEvents
{
    /**
     * Dispatch an event safely, respecting database transactions.
     *
     * If a database transaction is active, the event will be dispatched
     * after the transaction commits. Otherwise, it's dispatched immediately.
     *
     * @param  object  $event  The event to dispatch
     */
    protected function dispatchEvent(object $event): void
    {
        if (! $this->eventsEnabled || $this->events === null) {
            return;
        }

        // Check if we're inside a transaction
        $transactionLevel = DB::transactionLevel();

        if ($transactionLevel > 0) {
            // Queue event to be dispatched after commit
            DB::afterCommit(fn () => $this->events->dispatch($event));
        } else {
            // No transaction, dispatch immediately
            $this->events->dispatch($event);
        }
    }

    /**
     * Dispatch an event immediately, bypassing transaction safety.
     *
     * Use this only when you explicitly want the event to fire regardless
     * of transaction state (e.g., for logging or metrics).
     *
     * @param  object  $event  The event to dispatch
     */
    protected function dispatchEventNow(object $event): void
    {
        if (! $this->eventsEnabled || $this->events === null) {
            return;
        }

        $this->events->dispatch($event);
    }
}
