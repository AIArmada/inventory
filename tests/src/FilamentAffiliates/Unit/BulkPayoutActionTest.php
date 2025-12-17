<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Actions\BulkPayoutAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliatePayoutEvent::query()->delete();
    AffiliatePayoutMethod::query()->delete();
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();
});

it('has correct default name', function (): void {
    expect(BulkPayoutAction::getDefaultName())->toBe('bulk_process_payouts');
});

it('can be instantiated with make method', function (): void {
    $action = BulkPayoutAction::make('bulk_process_payouts');

    expect($action)->toBeInstanceOf(BulkPayoutAction::class);
});

it('processes a pending payout successfully', function (): void {
    $user = User::create([
        'name' => 'Payout User',
        'email' => 'payout-user@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'PAYOUT-' . Str::uuid(),
        'name' => 'Payout Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '123456789'],
        'is_verified' => true,
        'is_default' => true,
        'verified_at' => now(),
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAYOUT-REF-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 1500,
        'currency' => 'USD',
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
    ]);

    $factory = new PayoutProcessorFactory;
    $factory->register('bank_transfer', TestSuccessPayoutProcessor::class);
    app()->instance(PayoutProcessorFactory::class, $factory);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Completed)
        ->and($payout->paid_at)->not->toBeNull()
        ->and($payout->external_reference)->toBe('EXT-123');

    $event = $payout->events()->first();
    expect($event)->not->toBeNull()
        ->and($event->from_status)->toBe(PayoutStatus::Processing->value)
        ->and($event->to_status)->toBe(PayoutStatus::Completed->value)
        ->and($event->notes)->toBe('Payout processed successfully');
});

it('marks a pending payout as failed when no default payout method exists', function (): void {
    $user = User::create([
        'name' => 'No Method User',
        'email' => 'no-method@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'NOMETHOD-' . Str::uuid(),
        'name' => 'No Method Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAYOUT-REF-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 1500,
        'currency' => 'USD',
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
    ]);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Failed);

    $event = $payout->events()->first();
    expect($event)->not->toBeNull()
        ->and($event->to_status)->toBe(PayoutStatus::Failed->value)
        ->and($event->notes)->toBe('No default payout method configured');
});

it('marks a pending payout as failed when the processor fails', function (): void {
    $user = User::create([
        'name' => 'Fail User',
        'email' => 'fail-user@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'FAIL-' . Str::uuid(),
        'name' => 'Fail Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '123456789'],
        'is_verified' => true,
        'is_default' => true,
        'verified_at' => now(),
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAYOUT-REF-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 1500,
        'currency' => 'USD',
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
    ]);

    $factory = new PayoutProcessorFactory;
    $factory->register('bank_transfer', TestFailingPayoutProcessor::class);
    app()->instance(PayoutProcessorFactory::class, $factory);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Failed);

    $event = $payout->events()->first();
    expect($event)->not->toBeNull()
        ->and($event->to_status)->toBe(PayoutStatus::Failed->value)
        ->and($event->notes)->toBe('Processor failed');
});

it('marks a pending payout as failed when the processor throws', function (): void {
    $user = User::create([
        'name' => 'Throw User',
        'email' => 'throw-user@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'THROW-' . Str::uuid(),
        'name' => 'Throw Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '123456789'],
        'is_verified' => true,
        'is_default' => true,
        'verified_at' => now(),
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAYOUT-REF-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 1500,
        'currency' => 'USD',
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
    ]);

    $factory = new PayoutProcessorFactory;
    $factory->register('bank_transfer', TestThrowingPayoutProcessor::class);
    app()->instance(PayoutProcessorFactory::class, $factory);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Failed);

    $event = $payout->events()->first();
    expect($event)->not->toBeNull()
        ->and($event->to_status)->toBe(PayoutStatus::Failed->value)
        ->and($event->notes)->toBe('Processor exploded');
});

class TestSuccessPayoutProcessor implements PayoutProcessorInterface
{
    public function process(AffiliatePayout $payout): PayoutResult
    {
        return PayoutResult::success('EXT-123', ['processor' => 'test']);
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        return 'completed';
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
    {
        return null;
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'test-success';
    }
}

class TestFailingPayoutProcessor implements PayoutProcessorInterface
{
    public function process(AffiliatePayout $payout): PayoutResult
    {
        return PayoutResult::failure('Processor failed');
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        return 'failed';
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
    {
        return null;
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'test-failure';
    }
}

class TestThrowingPayoutProcessor implements PayoutProcessorInterface
{
    public function process(AffiliatePayout $payout): PayoutResult
    {
        throw new \Exception('Processor exploded');
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        return 'failed';
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
    {
        return null;
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'test-throwing';
    }
}
