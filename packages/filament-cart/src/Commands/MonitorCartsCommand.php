<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\FilamentCart\Data\AlertEvent;
use AIArmada\FilamentCart\Services\AlertDispatcher;
use AIArmada\FilamentCart\Services\AlertEvaluator;
use AIArmada\FilamentCart\Services\CartMonitor;
use Illuminate\Console\Command;

class MonitorCartsCommand extends Command
{
    protected $signature = 'cart:monitor
                            {--once : Run a single monitoring pass instead of continuous}
                            {--interval=10 : Monitoring interval in seconds (for continuous mode)}';

    protected $description = 'Monitor carts for abandonments, fraud signals, and alert triggers';

    public function __construct(
        private readonly CartMonitor $monitor,
        private readonly AlertEvaluator $evaluator,
        private readonly AlertDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $once = $this->option('once');
        $interval = (int) $this->option('interval');

        if ($once) {
            $this->info('Running single monitoring pass...');
            $this->runMonitoringPass();

            return self::SUCCESS;
        }

        $this->info("Starting continuous cart monitoring (interval: {$interval}s)");
        $this->info('Press Ctrl+C to stop.');
        $this->newLine();

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            $this->runMonitoringPass();
            sleep($interval);
        }

        return self::SUCCESS;
    }

    private function runMonitoringPass(): void
    {
        $timestamp = now()->format('H:i:s');

        // Detect abandonments
        $abandonments = $this->monitor->detectAbandonments();
        if ($abandonments->isNotEmpty()) {
            $this->warn("[{$timestamp}] Detected {$abandonments->count()} abandonments");
            $this->processEvents('abandonment', $abandonments);
        }

        // Detect fraud signals
        $fraudSignals = $this->monitor->detectFraudSignals();
        if ($fraudSignals->isNotEmpty()) {
            $this->error("[{$timestamp}] Detected {$fraudSignals->count()} fraud signals");
            $this->processEvents('fraud', $fraudSignals);
        }

        // Detect recovery opportunities
        $recoveryOpportunities = $this->monitor->detectRecoveryOpportunities();
        if ($recoveryOpportunities->isNotEmpty()) {
            $this->info("[{$timestamp}] Found {$recoveryOpportunities->count()} recovery opportunities");
            $this->processEvents('recovery', $recoveryOpportunities);
        }

        // High value carts
        $highValueCarts = $this->monitor->getHighValueCarts();
        if ($highValueCarts->isNotEmpty()) {
            $this->info("[{$timestamp}] Monitoring {$highValueCarts->count()} high-value carts");
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $items
     */
    private function processEvents(string $eventType, $items): void
    {
        foreach ($items as $item) {
            $eventData = (array) $item;

            // Find matching rules
            $matchingRules = $this->evaluator->getMatchingRules($eventType, $eventData);

            foreach ($matchingRules as $rule) {
                // Create appropriate event
                $event = match ($eventType) {
                    'abandonment' => AlertEvent::fromAbandonment(
                        $item->id,
                        $item->session_id ?? '',
                        $eventData,
                    ),
                    'fraud' => AlertEvent::fromFraud(
                        $item->id,
                        $item->session_id ?? '',
                        $eventData,
                    ),
                    'recovery' => AlertEvent::fromRecoveryOpportunity(
                        $item->id,
                        $item->session_id ?? '',
                        $eventData,
                    ),
                    'high_value' => AlertEvent::fromHighValue(
                        $item->id,
                        $item->session_id ?? '',
                        $eventData,
                    ),
                    default => AlertEvent::custom(
                        $eventType,
                        'info',
                        ucfirst($eventType) . ' Event',
                        "A {$eventType} event was detected.",
                        $eventData,
                        $item->id,
                        $item->session_id ?? null,
                    ),
                };

                // Dispatch alert
                $this->dispatcher->dispatch($rule, $event);

                $this->line("  → Alert dispatched: {$rule->name}");
            }
        }
    }
}
