# Filament Cashier Chip Vision: Implementation Roadmap

> **Document:** 05 of 05  
> **Package:** `aiarmada/filament-cashier-chip`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Overview

This document outlines the phased implementation plan for `aiarmada/filament-cashier-chip`. Each phase builds upon the previous, ensuring stable incremental delivery with working features at each milestone.

---

## Timeline Overview

```
Week 1-2: Foundation & Core Setup
    │
Week 3-4: Subscription Management Resource
    │
Week 5-6: Customer Billing Portal
    │
Week 7-8: Dashboard Widgets & Analytics
    │
Week 9-10: Invoicing & Reports
    │
Week 11-12: Polish, Testing & Documentation
```

---

## Phase 1: Foundation & Core Setup (Weeks 1-2)

### Objective
Establish package structure, service provider, and Filament plugin foundation.

### Deliverables

#### Package Structure
```
packages/filament-cashier-chip/
├── composer.json
├── config/
│   └── filament-cashier-chip.php
├── docs/
│   ├── index.md
│   ├── installation.md
│   └── vision/
│       └── (these documents)
├── resources/
│   ├── lang/
│   │   ├── en/
│   │   └── ms/
│   └── views/
│       ├── pages/
│       └── widgets/
├── src/
│   ├── FilamentCashierChipPlugin.php
│   ├── FilamentCashierChipServiceProvider.php
│   ├── Pages/
│   ├── Resources/
│   └── Widgets/
└── README.md
```

#### Configuration File
```php
// config/filament-cashier-chip.php
return [
    // Navigation
    'navigation' => [
        'group' => 'Billing',
        'sort' => 50,
    ],

    // Features
    'features' => [
        'subscriptions' => true,
        'invoices' => true,
        'customer_portal' => true,
        'dashboard' => true,
    ],

    // Tables
    'tables' => [
        'polling_interval' => '30s',
    ],

    // Customer Portal
    'portal' => [
        'panel_id' => 'billing',
        'middleware' => ['web', 'auth'],
    ],

    // Plan Labels (for display)
    'plan_labels' => [
        'price_basic_monthly' => 'Basic Monthly',
        'price_pro_monthly' => 'Pro Monthly',
        'price_premium_annual' => 'Premium Annual',
    ],
];
```

#### Service Provider
```php
class FilamentCashierChipServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-cashier-chip';

    protected array $resources = [];
    protected array $pages = [];
    protected array $widgets = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        // Verify cashier-chip is installed
        if (!class_exists(\AIArmada\CashierChip\Cashier::class)) {
            throw new \RuntimeException(
                'filament-cashier-chip requires aiarmada/cashier-chip package.'
            );
        }
    }
}
```

#### Plugin Class
```php
class FilamentCashierChipPlugin implements Plugin
{
    protected bool $customerPortalMode = false;
    protected bool $enableDashboard = true;
    protected bool $enableSubscriptions = true;
    protected bool $enableInvoices = true;
    protected ?string $navigationGroup = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-cashier-chip';
    }

    public function register(Panel $panel): void
    {
        if ($this->enableSubscriptions) {
            $panel->resources([
                SubscriptionResource::class,
            ]);
        }

        if ($this->enableDashboard && !$this->customerPortalMode) {
            $panel->pages([
                BillingDashboard::class,
            ]);
            $panel->widgets([
                MrrWidget::class,
                ActiveSubscribersWidget::class,
                RevenueChartWidget::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    // Configuration methods
    public function customerPortalMode(bool $enabled = true): static
    {
        $this->customerPortalMode = $enabled;
        return $this;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }
}
```

### Checklist
- [ ] Create package directory structure
- [ ] Set up `composer.json` with dependencies
- [ ] Create service provider
- [ ] Create plugin class with configuration
- [ ] Set up configuration file
- [ ] Create translation files structure
- [ ] Register with main application
- [ ] Verify dependency on `cashier-chip`

---

## Phase 2: Subscription Management (Weeks 3-4)

### Objective
Implement comprehensive SubscriptionResource with full CRUD and actions.

### Deliverables

#### SubscriptionResource
- Table with all relevant columns
- Status badge component
- Filters (status, plan, date range)
- Tabs (all, active, trialing, canceled)

#### View Page (Infolist)
- Overview section with key metrics
- Billing details section
- Trial information (conditional)
- Grace period information (conditional)

#### Actions
- Cancel subscription
- Cancel immediately
- Resume subscription
- Swap plan
- Update quantity
- Extend trial
- End trial immediately

#### Create Form
- Customer selection
- Plan selection
- Trial configuration
- Payment method selection

#### Bulk Actions
- Bulk cancel
- Export CSV

#### Relation Manager
- SubscriptionItemRelationManager
- Quantity updates

### Checklist
- [ ] `SubscriptionResource` with table
- [ ] Status badge component
- [ ] Filters and tabs
- [ ] View page with infolist
- [ ] Cancel action with confirmation
- [ ] Resume action
- [ ] Swap plan action with form
- [ ] Extend trial action
- [ ] Create subscription form
- [ ] Bulk actions
- [ ] Items relation manager
- [ ] Translations

---

## Phase 3: Customer Billing Portal (Weeks 5-6)

### Objective
Create self-hosted Filament panel for customer billing management.

### Deliverables

#### Portal Panel Configuration
- Dedicated panel provider
- Customer authentication
- Billable middleware

#### Dashboard Page
- Billing overview widget
- Current subscription card
- Payment method preview
- Recent invoices

#### Subscription Management
- View current subscription
- Change plan flow
- Cancel subscription flow
- Resume subscription

#### Payment Methods
- List payment methods
- Add via setup purchase
- Set default method
- Remove method

#### Invoice History
- List invoices
- Download PDF
- View details

#### Billing Settings
- Update billing information
- Sync to CHIP customer

### Checklist
- [ ] Create billing panel provider
- [ ] Billable middleware
- [ ] Dashboard with overview widget
- [ ] Subscription management page
- [ ] Change plan action with preview
- [ ] Cancel flow with feedback
- [ ] Resume action
- [ ] Payment methods page
- [ ] Add payment method flow
- [ ] Remove payment method action
- [ ] Invoice history page
- [ ] Download PDF action
- [ ] Billing settings page
- [ ] Portal URL generation helper
- [ ] Translations

---

## Phase 4: Dashboard Widgets (Weeks 7-8)

### Objective
Build comprehensive billing analytics dashboard for admins.

### Deliverables

#### Stats Widgets
- `MrrWidget` - Monthly Recurring Revenue
- `ActiveSubscribersWidget` - Subscriber counts
- `ChurnRateWidget` - Churn metrics

#### Chart Widgets
- `RevenueChartWidget` - 30-day trend
- `PlanDistributionWidget` - Doughnut chart

#### Advanced Widgets
- `TrialConversionsWidget` - Conversion funnel
- `AttentionRequiredWidget` - Issues summary

#### Dashboard Page
- Dedicated billing dashboard
- Configurable widget layout
- Export report functionality

### Checklist
- [ ] `MrrWidget` with trend
- [ ] `ActiveSubscribersWidget` with breakdown
- [ ] `ChurnRateWidget` with comparison
- [ ] `RevenueChartWidget` with dual axis
- [ ] `PlanDistributionWidget` doughnut
- [ ] `TrialConversionsWidget` with progress
- [ ] `AttentionRequiredWidget` with links
- [ ] `BillingDashboard` page
- [ ] Export report action
- [ ] Date range filters
- [ ] Widget caching
- [ ] Polling configuration

---

## Phase 5: Invoicing & Reports (Weeks 9-10)

### Objective
Complete invoice management and reporting functionality.

### Deliverables

#### InvoiceResource
- Invoice listing with status
- Invoice infolist with line items
- Mark as paid action
- Send invoice email action
- Download PDF action

#### Invoice Generation
- Create manual invoices
- Line item builder
- Tax calculation

#### Reports
- Revenue reports with export
- Subscription analytics
- Failed payment reports
- Churn analysis

### Checklist
- [ ] `InvoiceResource` with table
- [ ] Invoice status badges
- [ ] Invoice infolist
- [ ] Line items display
- [ ] Mark as paid action
- [ ] Send email action
- [ ] Download PDF action
- [ ] Create invoice form
- [ ] Line item builder
- [ ] Revenue report page
- [ ] Export functionality
- [ ] Scheduled reports (optional)

---

## Phase 6: Polish & Documentation (Weeks 11-12)

### Objective
Finalize package with testing, documentation, and polish.

### Deliverables

#### Testing
- Unit tests for services
- Feature tests for resources
- Widget tests
- Portal tests

#### Documentation
- Installation guide
- Configuration reference
- Usage examples
- API reference
- Customer portal setup

#### Polish
- UI/UX refinements
- Performance optimization
- Error handling
- Accessibility

### Checklist
- [ ] Unit tests (≥85% coverage)
- [ ] Feature tests for resources
- [ ] Widget rendering tests
- [ ] Portal flow tests
- [ ] PHPStan level 6 compliance
- [ ] Documentation site pages
- [ ] README update
- [ ] CHANGELOG
- [ ] Performance profiling
- [ ] Error message improvements
- [ ] Accessibility audit

---

## Dependencies

### Required Before Start
- [ ] `aiarmada/cashier-chip` package complete
- [ ] Subscription model with full lifecycle
- [ ] Invoice model with PDF generation
- [ ] PaymentMethod model

### Optional Integrations
- [ ] `filament-chip` for unified billing UI
- [ ] `filament-authz` for RBAC

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | ≥85% |
| PHPStan Level | 6 |
| Documentation Pages | 10+ |
| Widgets | 7 |
| Resources | 3 (Subscription, Invoice, Customer) |
| Translations | 2 (en, ms) |

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Cashier API changes | Pin cashier-chip version, integration tests |
| Filament upgrades | Follow Filament 5.x patterns, test against minor versions |
| Performance with large datasets | Caching, pagination, query optimization |
| Portal security | Middleware, scope queries to auth user |

---

## Post-Launch Roadmap

### v1.1
- Multi-currency support
- Additional payment gateway hints
- Advanced reporting

### v1.2
- Subscription pause/resume
- Usage-based billing UI
- Revenue forecasting widget

### v2.0
- Multi-tenancy support
- White-label portal
- API endpoints for headless use

---

## Navigation

**Previous:** [04-dashboard-widgets.md](04-dashboard-widgets.md)  
**Back to:** [PROGRESS.md](PROGRESS.md)
