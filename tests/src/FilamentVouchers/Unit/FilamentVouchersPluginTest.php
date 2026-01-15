<?php

declare(strict_types=1);

use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use AIArmada\FilamentVouchers\Pages\StackingConfigurationPage;
use AIArmada\FilamentVouchers\Pages\TargetingConfigurationPage;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\FilamentVouchers\Widgets\RedemptionTrendChart;
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;
use Filament\Panel;

it('exposes a stable plugin id', function (): void {
    expect((new FilamentVouchersPlugin)->getId())->toBe('filament-vouchers');
});

it('registers voucher resources, pages, and widgets', function (): void {
    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            VoucherResource::class,
            VoucherUsageResource::class,
            VoucherWalletResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([
            StackingConfigurationPage::class,
            TargetingConfigurationPage::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            VoucherStatsWidget::class,
            RedemptionTrendChart::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentVouchersPlugin)->register($panel);
});
