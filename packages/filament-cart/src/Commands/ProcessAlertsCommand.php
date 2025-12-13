<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\FilamentCart\Data\AlertEvent;
use AIArmada\FilamentCart\Models\AlertRule;
use AIArmada\FilamentCart\Services\AlertDispatcher;
use AIArmada\FilamentCart\Services\AlertEvaluator;
use Illuminate\Console\Command;

class ProcessAlertsCommand extends Command
{
    protected $signature = 'cart:process-alerts
                            {--rule= : Process a specific rule by ID}
                            {--event-type= : Process alerts for a specific event type}
                            {--dry-run : Show what would be processed without dispatching}';

    protected $description = 'Evaluate and dispatch cart alerts based on configured rules';

    public function __construct(
        private readonly AlertEvaluator $evaluator,
        private readonly AlertDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $ruleId = $this->option('rule');
        $eventType = $this->option('event-type');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No alerts will be dispatched');
            $this->newLine();
        }

        // Get rules to process
        $query = AlertRule::query()->where('is_active', true);

        if ($ruleId) {
            $query->where('id', $ruleId);
        }

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $rules = $query->orderBy('priority', 'desc')->get();

        if ($rules->isEmpty()) {
            $this->info('No active alert rules found.');

            return self::SUCCESS;
        }

        $this->info("Processing {$rules->count()} alert rule(s)...");
        $this->newLine();

        $processed = 0;
        $skipped = 0;
        $dispatched = 0;

        foreach ($rules as $rule) {
            $this->line("Rule: <info>{$rule->name}</info> ({$rule->event_type})");

            // Check cooldown
            if ($rule->isInCooldown()) {
                $remaining = $rule->getCooldownRemainingMinutes();
                $this->line("  ⏸ In cooldown ({$remaining} minutes remaining)");
                $skipped++;

                continue;
            }

            // For demonstration, we'll create a sample event to evaluate
            // In production, this would come from actual event sources
            $sampleEventData = $this->getSampleEventData($rule->event_type);

            if ($this->evaluator->evaluate($rule, $sampleEventData)) {
                $this->line('  ✓ Conditions matched');

                if (! $dryRun) {
                    $event = AlertEvent::custom(
                        $rule->event_type,
                        $rule->severity,
                        $rule->name,
                        $rule->description ?? "Alert triggered by rule: {$rule->name}",
                        $sampleEventData,
                    );

                    $log = $this->dispatcher->dispatch($rule, $event);
                    $this->line('  → Dispatched to: ' . implode(', ', $log->channels_notified));
                    $dispatched++;
                } else {
                    $this->line('  → Would dispatch to: ' . implode(', ', $rule->getEnabledChannels()));
                }
            } else {
                $this->line('  ✗ Conditions not matched');
            }

            $processed++;
        }

        $this->newLine();
        $this->info("Summary: {$processed} processed, {$skipped} skipped (cooldown), {$dispatched} dispatched");

        return self::SUCCESS;
    }

    /**
     * Get sample event data for testing rules.
     *
     * @return array<string, mixed>
     */
    private function getSampleEventData(string $eventType): array
    {
        return match ($eventType) {
            'abandonment' => [
                'cart_value_cents' => 15000,
                'items_count' => 3,
                'time_since_abandonment_minutes' => 45,
                'customer_type' => 'returning',
            ],
            'fraud' => [
                'risk_score' => 0.75,
                'ip_country' => 'US',
                'cart_value_cents' => 50000,
                'velocity_score' => 0.8,
            ],
            'high_value' => [
                'cart_value_cents' => 25000,
                'items_count' => 5,
                'customer_tier' => 'vip',
            ],
            'recovery' => [
                'cart_value_cents' => 8000,
                'abandonment_age_hours' => 2,
                'recovery_probability' => 0.65,
            ],
            default => [
                'event_type' => $eventType,
                'timestamp' => now()->toIso8601String(),
            ],
        };
    }
}
