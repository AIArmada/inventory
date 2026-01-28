<?php

declare(strict_types=1);

namespace AIArmada\Checkout;

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Exceptions\MissingPaymentGatewayException;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Steps\ApplyDiscountsStep;
use AIArmada\Checkout\Steps\CalculatePricingStep;
use AIArmada\Checkout\Steps\CalculateShippingStep;
use AIArmada\Checkout\Steps\CalculateTaxStep;
use AIArmada\Checkout\Steps\CreateOrderStep;
use AIArmada\Checkout\Steps\DispatchDocumentGenerationStep;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Steps\ReserveInventoryStep;
use AIArmada\Checkout\Steps\ResolveCustomerStep;
use AIArmada\Checkout\Steps\ValidateCartStep;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CheckoutServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('checkout')
            ->hasConfigFile()
            ->discoversMigrations();

        // Conditionally register routes
        if (config('checkout.routes.enabled', true)) {
            $package->hasRoute('checkout');
        }
    }

    public function registeringPackage(): void
    {
        $this->registerStepRegistry();
        $this->registerPaymentGatewayResolver();
        $this->registerCheckoutService();
    }

    public function bootingPackage(): void
    {
        $this->validateConfiguration('checkout', [
            'defaults.currency',
        ]);

        $this->validateOwnerConfiguration();
        $this->validatePaymentGatewayConfiguration();
        $this->registerDefaultSteps();
        $this->registerOptionalIntegrations();
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'checkout',
            CheckoutService::class,
            CheckoutServiceInterface::class,
            CheckoutStepRegistry::class,
            CheckoutStepRegistryInterface::class,
            PaymentGatewayResolver::class,
            PaymentGatewayResolverInterface::class,
        ];
    }

    protected function registerStepRegistry(): void
    {
        $this->app->singleton(CheckoutStepRegistry::class, function () {
            $registry = new CheckoutStepRegistry;

            $enabledSteps = config('checkout.steps.enabled', []);
            foreach ($enabledSteps as $step => $enabled) {
                if (! $enabled) {
                    $registry->disable($step);
                }
            }

            $order = config('checkout.steps.order', []);
            if (! empty($order)) {
                $registry->setOrder($order);
            }

            return $registry;
        });

        $this->app->alias(CheckoutStepRegistry::class, CheckoutStepRegistryInterface::class);
        $this->app->alias(CheckoutStepRegistry::class, 'checkout.steps');
    }

    protected function registerPaymentGatewayResolver(): void
    {
        $this->app->singleton(PaymentGatewayResolver::class, function () {
            $resolver = new PaymentGatewayResolver(
                config('checkout.payment.default_gateway'),
                config('checkout.payment.gateway_priority', ['cashier', 'cashier-chip', 'chip']),
            );

            $this->registerPaymentProcessors($resolver);

            return $resolver;
        });

        $this->app->alias(PaymentGatewayResolver::class, PaymentGatewayResolverInterface::class);
        $this->app->alias(PaymentGatewayResolver::class, 'checkout.payment');
    }

    protected function registerPaymentProcessors(PaymentGatewayResolver $resolver): void
    {
        // Priority: cashier → cashier-chip → chip
        $gateways = (array) config('checkout.payment.gateways', []);

        if (class_exists(\AIArmada\Cashier\GatewayManager::class) && ($gateways['cashier']['enabled'] ?? true)) {
            $resolver->register('cashier', $this->app->make(\AIArmada\Checkout\Integrations\Payment\CashierProcessor::class));
        }

        if (class_exists(\AIArmada\CashierChip\Cashier::class) && ($gateways['cashier-chip']['enabled'] ?? true)) {
            $resolver->register('cashier-chip', $this->app->make(\AIArmada\Checkout\Integrations\Payment\CashierChipProcessor::class));
        }

        if (class_exists(\AIArmada\Chip\Facades\Chip::class) && ($gateways['chip']['enabled'] ?? true)) {
            $resolver->register('chip', $this->app->make(\AIArmada\Checkout\Integrations\Payment\ChipProcessor::class));
        }
    }

    protected function registerCheckoutService(): void
    {
        $this->app->singleton(CheckoutService::class, fn ($app) => new CheckoutService(
            stepRegistry: $app->make(CheckoutStepRegistryInterface::class),
            events: $app->make(Dispatcher::class),
            paymentResolver: $app->make(PaymentGatewayResolverInterface::class),
        ));

        $this->app->alias(CheckoutService::class, CheckoutServiceInterface::class);
        $this->app->alias(CheckoutService::class, 'checkout');
    }

    protected function registerDefaultSteps(): void
    {
        $registry = $this->app->make(CheckoutStepRegistryInterface::class);

        // Core steps (always available)
        $registry->register('validate_cart', $this->app->make(ValidateCartStep::class));
        $registry->register('resolve_customer', $this->app->make(ResolveCustomerStep::class));
        $registry->register('calculate_pricing', $this->app->make(CalculatePricingStep::class));
        $registry->register('calculate_shipping', $this->app->make(CalculateShippingStep::class));
        $registry->register('process_payment', $this->app->make(ProcessPaymentStep::class));
        $registry->register('create_order', $this->app->make(CreateOrderStep::class));
        $registry->register('dispatch_documents', $this->app->make(DispatchDocumentGenerationStep::class));
    }

    protected function registerOptionalIntegrations(): void
    {
        $registry = $this->app->make(CheckoutStepRegistryInterface::class);

        // Inventory integration (optional)
        if ($this->hasInventoryPackage() && config('checkout.integrations.inventory.enabled', true)) {
            $registry->register('reserve_inventory', $this->app->make(ReserveInventoryStep::class));
        } else {
            $registry->disable('reserve_inventory');
        }

        // Tax integration (optional)
        if ($this->hasTaxPackage() && config('checkout.integrations.tax.enabled', true)) {
            $registry->register('calculate_tax', $this->app->make(CalculateTaxStep::class));
        } else {
            $registry->disable('calculate_tax');
        }

        // Discounts integration (promotions + vouchers, optional)
        if ($this->hasDiscountPackages() && $this->isDiscountsEnabled()) {
            $registry->register('apply_discounts', $this->app->make(ApplyDiscountsStep::class));
        } else {
            $registry->disable('apply_discounts');
        }
    }

    protected function validateOwnerConfiguration(): void
    {
        if (! config('checkout.owner.enabled', false)) {
            return;
        }

        if (! $this->app->bound(OwnerResolverInterface::class)) {
            throw new RuntimeException(
                'Checkout owner is enabled but no resolver is bound. ' .
                'Bind ' . OwnerResolverInterface::class . ' (recommended via COMMERCE_OWNER_RESOLVER / commerce-support config).'
            );
        }
    }

    protected function validatePaymentGatewayConfiguration(): void
    {
        // Check if at least one payment package exists
        $hasCashier = class_exists(\AIArmada\Cashier\GatewayManager::class);
        $hasCashierChip = class_exists(\AIArmada\CashierChip\Cashier::class);
        $hasChip = class_exists(\AIArmada\Chip\Facades\Chip::class);

        if (! $hasCashier && ! $hasCashierChip && ! $hasChip) {
            throw MissingPaymentGatewayException::noGatewayInstalled();
        }
    }

    protected function hasInventoryPackage(): bool
    {
        return class_exists(\AIArmada\Inventory\InventoryServiceProvider::class);
    }

    protected function hasTaxPackage(): bool
    {
        return class_exists(\AIArmada\Tax\TaxServiceProvider::class);
    }

    protected function hasDiscountPackages(): bool
    {
        return class_exists(\AIArmada\Promotions\PromotionsServiceProvider::class)
            || class_exists(\AIArmada\Vouchers\VouchersServiceProvider::class);
    }

    protected function isDiscountsEnabled(): bool
    {
        return config('checkout.integrations.promotions.enabled', true)
            || config('checkout.integrations.vouchers.enabled', true);
    }
}
