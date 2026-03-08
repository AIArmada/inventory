<?php

declare(strict_types=1);

namespace AIArmada\Inventory;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Facades\Cart;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\Inventory\Cart\CartManagerWithInventory;
use AIArmada\Inventory\Cart\ValidateInventoryOnAdd;
use AIArmada\Inventory\Console\CleanupExpiredAllocationsCommand;
use AIArmada\Inventory\Console\CreateValuationSnapshotCommand;
use AIArmada\Inventory\Contracts\CheckoutInventoryServiceInterface;
use AIArmada\Inventory\Exports\ExportService;
use AIArmada\Inventory\Integrations\CheckoutInventoryService;
use AIArmada\Inventory\Integrations\FulfillmentLocationService;
use AIArmada\Inventory\Listeners\CommitInventoryOnPayment;
use AIArmada\Inventory\Listeners\DeductInventoryFromOrder;
use AIArmada\Inventory\Listeners\ReleaseInventoryFromOrder;
use AIArmada\Inventory\Listeners\ReleaseInventoryOnCartClear;
use AIArmada\Inventory\Reports\InventoryKpiService;
use AIArmada\Inventory\Reports\MovementAnalysisReport;
use AIArmada\Inventory\Reports\StockLevelReport;
use AIArmada\Inventory\Services\BackorderService;
use AIArmada\Inventory\Services\BatchService;
use AIArmada\Inventory\Services\DemandForecastService;
use AIArmada\Inventory\Services\FifoCostService;
use AIArmada\Inventory\Services\InventoryAllocationService;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\ReplenishmentService;
use AIArmada\Inventory\Services\SerialLookupService;
use AIArmada\Inventory\Services\SerialService;
use AIArmada\Inventory\Services\StandardCostService;
use AIArmada\Inventory\Services\ValuationService;
use AIArmada\Inventory\Services\WeightedAverageCostService;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Events\InventoryReleaseRequired;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class InventoryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('inventory')
            ->hasConfigFile()
            ->discoversMigrations()
            ->hasCommands([
                CleanupExpiredAllocationsCommand::class,
                CreateValuationSnapshotCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerCoreServices();
        $this->registerBatchSerialServices();
        $this->registerCostingServices();
        $this->registerAllocationServices();
        $this->registerReplenishmentServices();
        $this->registerReportServices();
        $this->registerIntegrationServices();
    }

    public function packageBooted(): void
    {
        $this->registerCartIntegration();
        $this->registerPaymentIntegration();
        $this->registerOrdersIntegration();
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            InventoryService::class,
            InventoryAllocationService::class,
            BatchService::class,
            SerialService::class,
            SerialLookupService::class,
            FifoCostService::class,
            WeightedAverageCostService::class,
            StandardCostService::class,
            ValuationService::class,
            BackorderService::class,
            DemandForecastService::class,
            ReplenishmentService::class,
            InventoryKpiService::class,
            MovementAnalysisReport::class,
            StockLevelReport::class,
            ExportService::class,
            FulfillmentLocationService::class,
            CheckoutInventoryService::class,
            CheckoutInventoryServiceInterface::class,
            'inventory',
            'inventory.allocations',
        ];
    }

    /**
     * Register core inventory services.
     */
    private function registerCoreServices(): void
    {
        $this->app->singleton(InventoryService::class);
        $this->app->alias(InventoryService::class, 'inventory');

        $this->app->singleton(InventoryAllocationService::class);
        $this->app->alias(InventoryAllocationService::class, 'inventory.allocations');
    }

    /**
     * Register batch and serial number services.
     */
    private function registerBatchSerialServices(): void
    {
        $this->app->singleton(BatchService::class);
        $this->app->singleton(SerialService::class);
        $this->app->singleton(SerialLookupService::class);
    }

    /**
     * Register costing and valuation services.
     */
    private function registerCostingServices(): void
    {
        $this->app->singleton(FifoCostService::class);
        $this->app->singleton(WeightedAverageCostService::class);
        $this->app->singleton(StandardCostService::class);
        $this->app->singleton(ValuationService::class);
    }

    /**
     * Register allocation and backorder services.
     */
    private function registerAllocationServices(): void
    {
        $this->app->singleton(BackorderService::class);
    }

    /**
     * Register demand forecasting and replenishment services.
     */
    private function registerReplenishmentServices(): void
    {
        $this->app->singleton(DemandForecastService::class);
        $this->app->singleton(ReplenishmentService::class);
    }

    /**
     * Register reporting and analytics services.
     */
    private function registerReportServices(): void
    {
        $this->app->singleton(InventoryKpiService::class);
        $this->app->singleton(MovementAnalysisReport::class);
        $this->app->singleton(StockLevelReport::class);
        $this->app->singleton(ExportService::class);
    }

    /**
     * Register integration services for other packages.
     */
    private function registerIntegrationServices(): void
    {
        $this->app->singleton(FulfillmentLocationService::class);

        // Checkout integration service
        $this->app->singleton(CheckoutInventoryService::class);
        $this->app->bind(CheckoutInventoryServiceInterface::class, CheckoutInventoryService::class);
    }

    /**
     * Register cart package integration if available.
     */
    private function registerCartIntegration(): void
    {
        if (! config('inventory.cart.enabled', true)) {
            return;
        }

        // Check if cart package is installed
        if (! class_exists(CartManager::class)) {
            return;
        }

        // Register cart event listeners for inventory release
        if (class_exists(CartCleared::class)) {
            Event::listen(
                CartCleared::class,
                [ReleaseInventoryOnCartClear::class, 'handleCleared']
            );
        }

        if (class_exists(CartDestroyed::class)) {
            Event::listen(
                CartDestroyed::class,
                [ReleaseInventoryOnCartClear::class, 'handleDestroyed']
            );
        }

        // Register ItemAdded listener for validation/auto-allocation
        if (class_exists(ItemAdded::class)) {
            Event::listen(
                ItemAdded::class,
                ValidateInventoryOnAdd::class
            );
        }

        // Extend CartManager with inventory functionality
        $this->app->extend('cart', function ($manager, $app) {
            if ($manager instanceof CartManagerWithInventory) {
                return $manager;
            }

            $proxy = CartManagerWithInventory::fromCartManager($manager);
            $proxy->setAllocationService($app->make(InventoryAllocationService::class));

            // Update container bindings
            $app->instance(CartManager::class, $proxy);
            $app->instance(CartManagerInterface::class, $proxy);

            // Clear cached facade instance
            if (class_exists(Cart::class)) {
                Cart::clearResolvedInstance('cart');
            }

            return $proxy;
        });
    }

    /**
     * Register payment success listeners if payment packages are available.
     */
    private function registerPaymentIntegration(): void
    {
        if (! config('inventory.payment.auto_commit', true)) {
            return;
        }

        // CashierChip integration
        if (class_exists(PaymentSucceeded::class)) {
            Event::listen(
                PaymentSucceeded::class,
                CommitInventoryOnPayment::class
            );
        }

        // Cashier (gateway-agnostic) integration
        if (class_exists(\AIArmada\Cashier\Events\PaymentSucceeded::class)) {
            Event::listen(
                \AIArmada\Cashier\Events\PaymentSucceeded::class,
                CommitInventoryOnPayment::class
            );
        }

        // Generic payment success events (for custom implementations)
        $customEvents = config('inventory.payment.events', []);

        foreach ($customEvents as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, CommitInventoryOnPayment::class);
            }
        }
    }

    /**
     * Register orders package integration if available.
     */
    private function registerOrdersIntegration(): void
    {
        if (! config('inventory.orders.enabled', true)) {
            return;
        }

        // Inventory deduction on payment confirmation
        if (class_exists(InventoryDeductionRequired::class)) {
            Event::listen(
                InventoryDeductionRequired::class,
                DeductInventoryFromOrder::class
            );
        }

        // Inventory release on order cancellation
        if (class_exists(InventoryReleaseRequired::class)) {
            Event::listen(
                InventoryReleaseRequired::class,
                ReleaseInventoryFromOrder::class
            );
        }
    }
}
