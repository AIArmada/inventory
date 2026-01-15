<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip;

use AIArmada\FilamentCashierChip\Pages\BillingDashboard;
use AIArmada\FilamentCashierChip\Pages\Invoices;
use AIArmada\FilamentCashierChip\Pages\PaymentMethods;
use AIArmada\FilamentCashierChip\Pages\Subscriptions;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use AIArmada\FilamentCashierChip\Widgets\ActiveSubscribersWidget;
use AIArmada\FilamentCashierChip\Widgets\AttentionRequiredWidget;
use AIArmada\FilamentCashierChip\Widgets\ChurnRateWidget;
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use AIArmada\FilamentCashierChip\Widgets\RevenueChartWidget;
use AIArmada\FilamentCashierChip\Widgets\SubscriptionDistributionWidget;
use AIArmada\FilamentCashierChip\Widgets\TrialConversionsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentCashierChipPlugin implements Plugin
{
    private bool $hasSubscriptions = true;

    private bool $hasCustomers = true;

    private bool $hasInvoices = true;

    private bool $hasDashboardWidgets = true;

    private bool $hasBillingDashboard = true;

    private bool $hasBillingPortal = true;

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
        return 'filament-cashier-chip';
    }

    /**
     * Enable or disable the subscriptions resource.
     */
    public function subscriptions(bool $enabled = true): static
    {
        $this->hasSubscriptions = $enabled;

        return $this;
    }

    /**
     * Enable or disable the customers resource.
     */
    public function customers(bool $enabled = true): static
    {
        $this->hasCustomers = $enabled;

        return $this;
    }

    /**
     * Enable or disable the invoices resource.
     */
    public function invoices(bool $enabled = true): static
    {
        $this->hasInvoices = $enabled;

        return $this;
    }

    /**
     * Enable or disable dashboard widgets.
     */
    public function dashboardWidgets(bool $enabled = true): static
    {
        $this->hasDashboardWidgets = $enabled;

        return $this;
    }

    /**
     * Enable or disable the billing dashboard page.
     */
    public function billingDashboard(bool $enabled = true): static
    {
        $this->hasBillingDashboard = $enabled;

        return $this;
    }

    /**
     * Alias for billingDashboard() for API consistency with filament-cashier.
     */
    public function dashboard(bool $enabled = true): static
    {
        return $this->billingDashboard($enabled);
    }

    /**
     * Enable or disable the billing portal pages (subscriptions, payment methods, invoices).
     */
    public function billingPortal(bool $enabled = true): static
    {
        $this->hasBillingPortal = $enabled;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $widgets = [];
        $pages = [];

        if ($this->hasSubscriptions && config('filament-cashier-chip.features.subscriptions', true)) {
            $resources[] = SubscriptionResource::class;
        }

        if ($this->hasCustomers && config('filament-cashier-chip.features.customers', true)) {
            $resources[] = CustomerResource::class;
        }

        if ($this->hasInvoices && config('filament-cashier-chip.features.invoices', true)) {
            $resources[] = InvoiceResource::class;
        }

        if ($this->hasDashboardWidgets && config('filament-cashier-chip.features.dashboard_widgets', true)) {
            if (config('filament-cashier-chip.features.dashboard.widgets.mrr', true)) {
                $widgets[] = MRRWidget::class;
            }
            if (config('filament-cashier-chip.features.dashboard.widgets.active_subscribers', true)) {
                $widgets[] = ActiveSubscribersWidget::class;
            }
            if (config('filament-cashier-chip.features.dashboard.widgets.churn_rate', true)) {
                $widgets[] = ChurnRateWidget::class;
            }
            if (config('filament-cashier-chip.features.dashboard.widgets.attention_required', true)) {
                $widgets[] = AttentionRequiredWidget::class;
            }
            if (config('filament-cashier-chip.features.dashboard.widgets.revenue_chart', true)) {
                $widgets[] = RevenueChartWidget::class;
            }
            if (config('filament-cashier-chip.features.dashboard.widgets.subscription_distribution', true)) {
                $widgets[] = SubscriptionDistributionWidget::class;
            }
            if (config('filament-cashier-chip.features.dashboard.widgets.trial_conversions', true)) {
                $widgets[] = TrialConversionsWidget::class;
            }
        }

        if ($this->hasBillingDashboard) {
            $pages[] = BillingDashboard::class;
        }

        if ($this->hasBillingPortal) {
            if (config('filament-cashier-chip.billing.features.subscriptions', true)) {
                $pages[] = Subscriptions::class;
            }
            if (config('filament-cashier-chip.billing.features.payment_methods', true)) {
                $pages[] = PaymentMethods::class;
            }
            if (config('filament-cashier-chip.billing.features.invoices', true)) {
                $pages[] = Invoices::class;
            }
        }

        $panel
            ->resources($resources)
            ->widgets($widgets)
            ->pages($pages);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
