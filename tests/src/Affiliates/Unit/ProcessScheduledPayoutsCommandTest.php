<?php

declare(strict_types=1);

use AIArmada\Affiliates\Console\Commands\ProcessScheduledPayoutsCommand;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'PROCESS-' . uniqid(),
        'name' => 'Process Test Affiliate',
        'contact_email' => 'process@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('ProcessScheduledPayoutsCommand', function (): void {
    test('command exists and is registered', function (): void {
        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);
        expect($result)->toBeIn([0, 1]); // 0=success, 1=failure
    });

    test('processes affiliates with sufficient balance', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Would create payout');
    });

    test('skips affiliates below minimum amount', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 1000, // Below default 5000
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Skipped: 0');
    });

    test('respects custom minimum amount option', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 2000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 1000,
            'currency' => 'USD',
        ]);

        $result = Artisan::call('affiliates:process-payouts', [
            '--dry-run' => true,
            '--min-amount' => 1500,
        ]);

        $output = Artisan::output();
        expect($output)->toContain('Would create payout');
    });

    test('filters by specific affiliate', function (): void {
        $otherAffiliate = Affiliate::create([
            'code' => 'OTHER-' . uniqid(),
            'name' => 'Other Affiliate',
            'contact_email' => 'other@example.com',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        AffiliateBalance::create([
            'affiliate_id' => $otherAffiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        $result = Artisan::call('affiliates:process-payouts', [
            '--dry-run' => true,
            '--affiliate' => $this->affiliate->id,
        ]);

        $output = Artisan::output();
        expect($output)->toContain('Processed: 1');
    });

    test('skips inactive affiliates', function (): void {
        $this->affiliate->update(['status' => AffiliateStatus::Paused]);

        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Skipped: 0');
        expect($output)->toContain('Processed: 0');
    });

    test('skips affiliates with active holds', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'Pending review',
            'expires_at' => null, // Permanent hold
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Skipped: 1');
    });

    test('skips affiliates with pending payouts', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        AffiliatePayout::create([
            'reference' => 'PAY-PENDING-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'amount_minor' => 5000,
            'currency' => 'USD',
            'status' => PayoutStatus::Pending,
            'method' => 'bank_transfer',
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Skipped: 1');
    });

    test('skips affiliates with processing payouts', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        AffiliatePayout::create([
            'reference' => 'PAY-PROCESSING-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'amount_minor' => 5000,
            'currency' => 'USD',
            'status' => PayoutStatus::Processing,
            'method' => 'bank_transfer',
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Skipped: 1');
    });

    test('does not skip affiliates with expired holds', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'Expired hold',
            'expires_at' => now()->subDay(), // Expired
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Would create payout');
    });

    test('does not skip affiliates with completed payouts', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        AffiliatePayout::create([
            'reference' => 'PAY-COMPLETED-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'amount_minor' => 5000,
            'currency' => 'USD',
            'status' => PayoutStatus::Completed,
            'method' => 'bank_transfer',
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Would create payout');
    });

    test('displays correct processed count', function (): void {
        $secondAffiliate = Affiliate::create([
            'code' => 'SECOND-' . uniqid(),
            'name' => 'Second Affiliate',
            'contact_email' => 'second@example.com',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        AffiliateBalance::create([
            'affiliate_id' => $secondAffiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        $result = Artisan::call('affiliates:process-payouts', ['--dry-run' => true]);

        $output = Artisan::output();
        expect($output)->toContain('Processed: 2');
    });

    test('creates payout when not in dry-run mode', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        // Count payouts before
        $payoutsBefore = AffiliatePayout::where('owner_id', $this->affiliate->id)->count();

        Artisan::call('affiliates:process-payouts');

        // Should have created a payout
        $payoutsAfter = AffiliatePayout::where('owner_id', $this->affiliate->id)->count();
        expect($payoutsAfter)->toBe($payoutsBefore + 1);

        // Balance should be deducted
        $balance->refresh();
        expect($balance->available_minor)->toBe(0);
    });

    test('creates payout event when processing', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        Artisan::call('affiliates:process-payouts');

        $payout = AffiliatePayout::where('owner_id', $this->affiliate->id)->first();

        // Should have created an event
        $eventCount = $payout->events()->count();
        expect($eventCount)->toBeGreaterThanOrEqual(1);
    });

    test('links conversions to payout when processing', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        // Create an approved conversion without payout
        $conversion = \AIArmada\Affiliates\Models\AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORDER-' . uniqid(),
            'subtotal_minor' => 50000,
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => \AIArmada\Affiliates\Enums\ConversionStatus::Approved->value,
            'occurred_at' => now()->subDays(30),
            'affiliate_payout_id' => null,
        ]);

        Artisan::call('affiliates:process-payouts');

        $conversion->refresh();
        $payout = AffiliatePayout::where('owner_id', $this->affiliate->id)->first();

        // Conversion should be linked to the payout
        expect($conversion->affiliate_payout_id)->toBe($payout->id);
    });

    test('sets payout status to pending when created', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 10000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        Artisan::call('affiliates:process-payouts');

        $payout = AffiliatePayout::where('owner_id', $this->affiliate->id)->first();

        expect($payout->status)->toBe(PayoutStatus::Pending);
    });

    test('sets correct payout amount from balance', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 15000,
            'pending_minor' => 0,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        Artisan::call('affiliates:process-payouts');

        $payout = AffiliatePayout::where('owner_id', $this->affiliate->id)->first();

        expect($payout->total_minor)->toBe(15000);
        expect($payout->currency)->toBe('USD');
    });
});

describe('ProcessScheduledPayoutsCommand class structure', function (): void {
    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(ProcessScheduledPayoutsCommand::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has correct signature', function (): void {
        $reflection = new ReflectionClass(ProcessScheduledPayoutsCommand::class);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        $command = app(ProcessScheduledPayoutsCommand::class);
        $signature = $property->getValue($command);

        expect($signature)->toContain('affiliates:process-payouts');
        expect($signature)->toContain('--dry-run');
        expect($signature)->toContain('--affiliate');
        expect($signature)->toContain('--min-amount');
    });
});
