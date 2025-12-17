<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentVouchers\Models\Voucher as FilamentVoucher;
use AIArmada\FilamentVouchers\Resources\CampaignResource;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource;
use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\Models\VoucherFraudSignal;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

uses(TestCase::class);

final class TestOwnerResolver implements OwnerResolverInterface
{
    public function __construct(private ?Model $owner)
    {
    }

    public function resolve(): ?Model
    {
        return $this->owner;
    }
}

it('scopes Filament Vouchers resources to the resolved owner (including global)', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new TestOwnerResolver($ownerA));

    $globalVoucher = FilamentVoucher::query()->create([
        'code' => 'GLOBAL-1',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $ownerAVoucher = FilamentVoucher::query()->create([
        'code' => 'A-1',
        'name' => 'Owner A Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $ownerAVoucher->assignOwner($ownerA)->save();

    $ownerBVoucher = FilamentVoucher::query()->create([
        'code' => 'B-1',
        'name' => 'Owner B Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $ownerBVoucher->assignOwner($ownerB)->save();

    $globalUsage = VoucherUsage::query()->create([
        'voucher_id' => $globalVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $ownerAUsage = VoucherUsage::query()->create([
        'voucher_id' => $ownerAVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $ownerBUsage = VoucherUsage::query()->create([
        'voucher_id' => $ownerBVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $globalWallet = VoucherWallet::query()->create([
        'voucher_id' => $globalVoucher->id,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'is_claimed' => true,
        'claimed_at' => now(),
        'is_redeemed' => false,
    ]);

    $ownerBWallet = VoucherWallet::query()->create([
        'voucher_id' => $ownerBVoucher->id,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'is_claimed' => true,
        'claimed_at' => now(),
        'is_redeemed' => false,
    ]);

    $globalCampaign = Campaign::query()->create([
        'name' => 'Global Campaign',
        'slug' => 'global-campaign',
        'status' => CampaignStatus::Active->value,
    ]);

    $ownerACampaign = Campaign::query()->create([
        'name' => 'Owner A Campaign',
        'slug' => 'a-campaign',
        'status' => CampaignStatus::Active->value,
    ]);
    $ownerACampaign->assignOwner($ownerA)->save();

    $ownerBCampaign = Campaign::query()->create([
        'name' => 'Owner B Campaign',
        'slug' => 'b-campaign',
        'status' => CampaignStatus::Active->value,
    ]);
    $ownerBCampaign->assignOwner($ownerB)->save();

    $globalGiftCard = GiftCard::query()->create([
        'code' => 'GC-GLOBAL',
        'initial_balance' => 1000,
        'current_balance' => 1000,
        'currency' => 'USD',
        'status' => GiftCardStatus::Active->value,
    ]);

    $ownerAGiftCard = GiftCard::query()->create([
        'code' => 'GC-A',
        'initial_balance' => 1000,
        'current_balance' => 1000,
        'currency' => 'USD',
        'status' => GiftCardStatus::Active->value,
    ]);
    $ownerAGiftCard->assignOwner($ownerA)->save();

    $ownerBGiftCard = GiftCard::query()->create([
        'code' => 'GC-B',
        'initial_balance' => 1000,
        'current_balance' => 1000,
        'currency' => 'USD',
        'status' => GiftCardStatus::Active->value,
    ]);
    $ownerBGiftCard->assignOwner($ownerB)->save();

    $globalFraud = VoucherFraudSignal::query()->create([
        'voucher_id' => $globalVoucher->id,
        'voucher_code' => $globalVoucher->code,
        'signal_type' => FraudSignalType::IpAddressAnomaly->value,
        'score' => 0.9,
        'risk_level' => FraudRiskLevel::High->value,
        'message' => 'Global signal',
        'detector' => 'test',
        'was_blocked' => false,
        'reviewed' => false,
        'user_id' => (string) Str::uuid(),
    ]);

    $ownerAFraud = VoucherFraudSignal::query()->create([
        'voucher_id' => $ownerAVoucher->id,
        'voucher_code' => $ownerAVoucher->code,
        'signal_type' => FraudSignalType::IpAddressAnomaly->value,
        'score' => 0.9,
        'risk_level' => FraudRiskLevel::High->value,
        'message' => 'Owner A signal',
        'detector' => 'test',
        'was_blocked' => false,
        'reviewed' => false,
        'user_id' => (string) Str::uuid(),
    ]);

    $ownerBFraud = VoucherFraudSignal::query()->create([
        'voucher_id' => $ownerBVoucher->id,
        'voucher_code' => $ownerBVoucher->code,
        'signal_type' => FraudSignalType::IpAddressAnomaly->value,
        'score' => 0.9,
        'risk_level' => FraudRiskLevel::High->value,
        'message' => 'Owner B signal',
        'detector' => 'test',
        'was_blocked' => false,
        'reviewed' => false,
        'user_id' => (string) Str::uuid(),
    ]);

    $vouchers = VoucherResource::getEloquentQuery()->pluck('id')->all();
    expect($vouchers)->toContain($globalVoucher->id, $ownerAVoucher->id)
        ->not->toContain($ownerBVoucher->id);

    $usages = VoucherUsageResource::getEloquentQuery()->pluck('id')->all();
    expect($usages)->toContain($globalUsage->id, $ownerAUsage->id)
        ->not->toContain($ownerBUsage->id);

    $wallets = VoucherWalletResource::getEloquentQuery()->pluck('id')->all();
    expect($wallets)->toContain($globalWallet->id)
        ->not->toContain($ownerBWallet->id);

    $campaigns = CampaignResource::getEloquentQuery()->pluck('id')->all();
    expect($campaigns)->toContain($globalCampaign->id, $ownerACampaign->id)
        ->not->toContain($ownerBCampaign->id);

    $giftCards = GiftCardResource::getEloquentQuery()->pluck('id')->all();
    expect($giftCards)->toContain($globalGiftCard->id, $ownerAGiftCard->id)
        ->not->toContain($ownerBGiftCard->id);

    $signals = FraudSignalResource::getEloquentQuery()->pluck('id')->all();
    expect($signals)->toContain($globalFraud->id, $ownerAFraud->id)
        ->not->toContain($ownerBFraud->id);
});

it('can exclude global records from Filament Vouchers resources', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-2@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new TestOwnerResolver($ownerA));

    $globalVoucher = FilamentVoucher::query()->create([
        'code' => 'GLOBAL-2',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $ownerAVoucher = FilamentVoucher::query()->create([
        'code' => 'A-2',
        'name' => 'Owner A Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $ownerAVoucher->assignOwner($ownerA)->save();

    $globalUsage = VoucherUsage::query()->create([
        'voucher_id' => $globalVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $ownerAUsage = VoucherUsage::query()->create([
        'voucher_id' => $ownerAVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $globalCampaign = Campaign::query()->create([
        'name' => 'Global Campaign 2',
        'slug' => 'global-campaign-2',
        'status' => CampaignStatus::Active->value,
    ]);

    $ownerACampaign = Campaign::query()->create([
        'name' => 'Owner A Campaign 2',
        'slug' => 'a-campaign-2',
        'status' => CampaignStatus::Active->value,
    ]);
    $ownerACampaign->assignOwner($ownerA)->save();

    $globalGiftCard = GiftCard::query()->create([
        'code' => 'GC-GLOBAL-2',
        'initial_balance' => 1000,
        'current_balance' => 1000,
        'currency' => 'USD',
        'status' => GiftCardStatus::Active->value,
    ]);

    $ownerAGiftCard = GiftCard::query()->create([
        'code' => 'GC-A-2',
        'initial_balance' => 1000,
        'current_balance' => 1000,
        'currency' => 'USD',
        'status' => GiftCardStatus::Active->value,
    ]);
    $ownerAGiftCard->assignOwner($ownerA)->save();

    $globalFraud = VoucherFraudSignal::query()->create([
        'voucher_id' => $globalVoucher->id,
        'voucher_code' => $globalVoucher->code,
        'signal_type' => FraudSignalType::IpAddressAnomaly->value,
        'score' => 0.9,
        'risk_level' => FraudRiskLevel::High->value,
        'message' => 'Global signal 2',
        'detector' => 'test',
        'was_blocked' => false,
        'reviewed' => false,
        'user_id' => (string) Str::uuid(),
    ]);

    $ownerAFraud = VoucherFraudSignal::query()->create([
        'voucher_id' => $ownerAVoucher->id,
        'voucher_code' => $ownerAVoucher->code,
        'signal_type' => FraudSignalType::IpAddressAnomaly->value,
        'score' => 0.9,
        'risk_level' => FraudRiskLevel::High->value,
        'message' => 'Owner A signal 2',
        'detector' => 'test',
        'was_blocked' => false,
        'reviewed' => false,
        'user_id' => (string) Str::uuid(),
    ]);

    $vouchers = VoucherResource::getEloquentQuery()->pluck('id')->all();
    expect($vouchers)->toContain($ownerAVoucher->id)
        ->not->toContain($globalVoucher->id);

    $usages = VoucherUsageResource::getEloquentQuery()->pluck('id')->all();
    expect($usages)->toContain($ownerAUsage->id)
        ->not->toContain($globalUsage->id);

    $campaigns = CampaignResource::getEloquentQuery()->pluck('id')->all();
    expect($campaigns)->toContain($ownerACampaign->id)
        ->not->toContain($globalCampaign->id);

    $giftCards = GiftCardResource::getEloquentQuery()->pluck('id')->all();
    expect($giftCards)->toContain($ownerAGiftCard->id)
        ->not->toContain($globalGiftCard->id);

    $signals = FraudSignalResource::getEloquentQuery()->pluck('id')->all();
    expect($signals)->toContain($ownerAFraud->id)
        ->not->toContain($globalFraud->id);
});
