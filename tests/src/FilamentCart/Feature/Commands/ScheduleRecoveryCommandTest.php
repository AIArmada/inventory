<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Commands\ScheduleRecoveryCommand;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Services\RecoveryScheduler;

describe('ScheduleRecoveryCommand', function (): void {
    it('runs successfully with no active campaigns', function (): void {
        $scheduler = Mockery::mock(RecoveryScheduler::class);
        $this->app->instance(RecoveryScheduler::class, $scheduler);

        $this->artisan('cart:schedule-recovery')
            ->expectsOutput('No active campaigns found.')
            ->assertSuccessful();
    });

    it('schedules recovery for active campaigns', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Active Campaign',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'strategy' => 'email',
            'trigger_type' => 'abandonment',
        ]);

        $scheduler = Mockery::mock(RecoveryScheduler::class);
        $scheduler->shouldReceive('scheduleForCampaign')
            ->once()
            ->with(Mockery::on(fn($arg) => $arg->id === $campaign->id))
            ->andReturn(5);

        $this->app->instance(RecoveryScheduler::class, $scheduler);

        $this->artisan('cart:schedule-recovery')
            ->expectsOutputToContain('Processing 1 campaign(s)...')
            ->expectsOutputToContain("Campaign: {$campaign->name}")
            ->expectsOutputToContain('Scheduled: 5 attempts')
            ->assertSuccessful();
    });

    it('supports dry run mode', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Dry Run Campaign',
            'status' => 'active',
            'strategy' => 'sms',
            'trigger_type' => 'abandonment',
        ]);

        $scheduler = Mockery::mock(RecoveryScheduler::class);
        // Should NOT call scheduleForCampaign

        $this->app->instance(RecoveryScheduler::class, $scheduler);

        $this->artisan('cart:schedule-recovery', ['--dry-run' => true])
            ->expectsOutput('Running in dry-run mode. No changes will be made.')
            ->expectsOutputToContain('[DRY-RUN] Would process campaign')
            ->assertSuccessful();
    });

    it('filters by campaign id', function (): void {
        $campaign1 = RecoveryCampaign::create(['name' => 'C1', 'status' => 'active', 'strategy' => 'email', 'trigger_type' => 'abandonment']);
        $campaign2 = RecoveryCampaign::create(['name' => 'C2', 'status' => 'active', 'strategy' => 'email', 'trigger_type' => 'abandonment']);

        $scheduler = Mockery::mock(RecoveryScheduler::class);
        $scheduler->shouldReceive('scheduleForCampaign')
            ->once()
            ->andReturn(1);

        $this->app->instance(RecoveryScheduler::class, $scheduler);

        $this->artisan('cart:schedule-recovery', ['--campaign' => $campaign1->id])
            ->assertSuccessful();
    });
});
