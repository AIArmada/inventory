<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier;

use AIArmada\FilamentCashier\Pages\BillingDashboard;
use AIArmada\FilamentCashier\Pages\GatewayManagement;
use AIArmada\FilamentCashier\Pages\GatewaySetup;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Widgets\GatewayBreakdownWidget;
use AIArmada\FilamentCashier\Widgets\GatewayComparisonWidget;
use AIArmada\FilamentCashier\Widgets\TotalMrrWidget;
use AIArmada\FilamentCashier\Widgets\TotalSubscribersWidget;
use AIArmada\FilamentCashier\Widgets\UnifiedChurnWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentCashierPlugin implements Plugin
{
    private bool $enableDashboard = true;

    private bool $enableSubscriptions = true;

    private bool $enableInvoices = true;

    private bool $enableGatewayManagement = false;

    private bool $customerPortalMode = false;

    private ?string $navigationGroup = null;

    private ?int $navigationSort = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-cashier';
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /**
     * Enable or disable the billing dashboard.
     */
    public function dashboard(bool $enabled = true): static
    {
        $this->enableDashboard = $enabled;

        return $this;
    }

    /**
     * Enable or disable the subscriptions resource.
     */
    public function subscriptions(bool $enabled = true): static
    {
        $this->enableSubscriptions = $enabled;

        return $this;
    }

    /**
     * Enable or disable the invoices resource.
     */
    public function invoices(bool $enabled = true): static
    {
        $this->enableInvoices = $enabled;

        return $this;
    }

    /**
     * Enable or disable gateway management.
     */
    public function gatewayManagement(bool $enabled = true): static
    {
        $this->enableGatewayManagement = $enabled;

        return $this;
    }

    /**
     * Enable customer portal mode (hides admin-only features).
     */
    public function customerPortalMode(bool $enabled = true): static
    {
        $this->customerPortalMode = $enabled;

        return $this;
    }

    // Legacy method aliases for backward compatibility

    /** @deprecated Use dashboard() instead */
    public function enableDashboard(bool $enable = true): static
    {
        return $this->dashboard($enable);
    }

    /** @deprecated Use subscriptions() instead */
    public function enableSubscriptions(bool $enable = true): static
    {
        return $this->subscriptions($enable);
    }

    /** @deprecated Use invoices() instead */
    public function enableInvoices(bool $enable = true): static
    {
        return $this->invoices($enable);
    }

    /** @deprecated Use gatewayManagement() instead */
    public function enableGatewayManagement(bool $enable = true): static
    {
        return $this->gatewayManagement($enable);
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup ?? config('filament-cashier.navigation.group', 'Billing');
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort ?? config('filament-cashier.navigation.sort', 50);
    }

    public function register(Panel $panel): void
    {
        $gateways = app(GatewayDetector::class)->availableGateways();

        $customerPortalMode = $this->customerPortalMode || (bool) config('filament-cashier.features.customer_portal', false);
        $enableDashboard = $this->enableDashboard && (bool) config('filament-cashier.features.dashboard', true);
        $enableSubscriptions = $this->enableSubscriptions && (bool) config('filament-cashier.features.subscriptions', true);
        $enableInvoices = $this->enableInvoices && (bool) config('filament-cashier.features.invoices', true);
        $enableGatewayManagement = $this->enableGatewayManagement && (bool) config('filament-cashier.features.gateway_management', false);

        if ($gateways->isEmpty()) {
            $panel->pages([GatewaySetup::class]);

            return;
        }

        $resources = [];
        $pages = [];
        $widgets = [];

        if ($enableSubscriptions) {
            $resources[] = UnifiedSubscriptionResource::class;
        }

        if ($enableInvoices) {
            $resources[] = UnifiedInvoiceResource::class;
        }

        if ($enableDashboard && ! $customerPortalMode) {
            $pages[] = BillingDashboard::class;
            $widgets = [
                TotalMrrWidget::class,
                TotalSubscribersWidget::class,
                GatewayBreakdownWidget::class,
                GatewayComparisonWidget::class,
                UnifiedChurnWidget::class,
            ];
        }

        if ($enableGatewayManagement && ! $customerPortalMode) {
            $pages[] = GatewayManagement::class;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }

        if ($pages !== []) {
            $panel->pages($pages);
        }

        if ($widgets !== []) {
            $panel->widgets($widgets);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
