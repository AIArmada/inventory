<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Actions\ActivateVoucherAction;
use AIArmada\FilamentVouchers\Actions\AddToMyWalletAction;
use AIArmada\FilamentVouchers\Actions\ApplyVoucherToCartAction;
use AIArmada\FilamentVouchers\Actions\BulkGenerateVouchersAction;
use AIArmada\FilamentVouchers\Actions\ManualRedeemVoucherAction;
use AIArmada\FilamentVouchers\Actions\PauseVoucherAction;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use AIArmada\FilamentVouchers\Pages\StackingConfigurationPage;
use AIArmada\FilamentVouchers\Pages\TargetingConfigurationPage;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\CreateVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\EditVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\ListVouchers;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\ViewVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers\VoucherUsagesRelationManager;
use AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers\WalletEntriesRelationManager;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherForm;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherInfolist;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Tables\VouchersTable;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Tables\WalletEntriesTable;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Pages\ListVoucherUsages;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Tables\VoucherUsagesTable;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource\Tables\VoucherWalletsTable;
use AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget;
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;
use AIArmada\FilamentVouchers\Widgets\RedemptionTrendChart;
use AIArmada\FilamentVouchers\Widgets\VoucherCartStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherWalletStatsWidget;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

afterEach(function (): void {
    \Mockery::close();
});

function makeVouchersTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = \Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('builds resources, schemas, tables, relation managers, pages, widgets, and actions', function (): void {
    // Resources
    foreach ([
        VoucherResource::class,
        VoucherUsageResource::class,
        VoucherWalletResource::class,
    ] as $resource) {
        expect($resource::form(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::infolist(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::table(makeVouchersTable()))->toBeInstanceOf(Table::class);
        expect($resource::getPages())->toBeArray();
    }

    // Schema/table builders
    expect(VoucherForm::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(VoucherInfolist::configure(Schema::make()))->toBeInstanceOf(Schema::class);
    expect(VouchersTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);
    expect(WalletEntriesTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);

    expect(VoucherUsagesTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);
    expect(VoucherWalletsTable::configure(makeVouchersTable()))->toBeInstanceOf(Table::class);

    // Relation managers
    foreach ([
        VoucherUsagesRelationManager::class,
        WalletEntriesRelationManager::class,
    ] as $manager) {
        $instance = app($manager);
        expect($instance->table(makeVouchersTable()))->toBeInstanceOf(Table::class);
    }

    // Resource pages: invoke protected action builders via reflection.
    foreach ([
        EditVoucher::class,
        ListVouchers::class,
        ViewVoucher::class,
        ListVoucherUsages::class,
    ] as $pageClass) {
        $page = app($pageClass);

        $method = new ReflectionMethod($pageClass, 'getHeaderActions');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBeArray();
    }

    foreach ([CreateVoucher::class] as $pageClass) {
        $page = app($pageClass);

        $method = new ReflectionMethod($pageClass, 'getFormActions');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBeArray();
    }

    // Standalone pages
    foreach ([
        StackingConfigurationPage::class,
        TargetingConfigurationPage::class,
    ] as $page) {
        expect(app($page))->toBeInstanceOf($page);
    }

    // Widgets
    foreach ([
        VoucherStatsWidget::class,
        RedemptionTrendChart::class,
        AppliedVoucherBadgesWidget::class,
        AppliedVouchersWidget::class,
        QuickApplyVoucherWidget::class,
        VoucherSuggestionsWidget::class,
        VoucherUsageTimelineWidget::class,
        VoucherWalletStatsWidget::class,
        VoucherCartStatsWidget::class,
    ] as $widget) {
        expect(app($widget))->toBeInstanceOf($widget);
    }

    // Actions
    foreach ([
        ActivateVoucherAction::class,
        PauseVoucherAction::class,
        AddToMyWalletAction::class,
        ManualRedeemVoucherAction::class,
        BulkGenerateVouchersAction::class,
        ApplyVoucherToCartAction::class,
    ] as $action) {
        expect($action::make())->toBeObject();
    }

    // Extensions
    expect(CartVoucherActions::applyVoucher())->toBeInstanceOf(\Filament\Actions\Action::class);
    expect(CartVoucherActions::showAppliedVouchers())->toBeInstanceOf(\Filament\Actions\Action::class);
    expect(CartVoucherActions::removeVoucher('TEST'))->toBeInstanceOf(\Filament\Actions\Action::class);

    // Plugin
    $panel = Panel::make()->id('admin');
    (new FilamentVouchersPlugin)->register($panel);
});
