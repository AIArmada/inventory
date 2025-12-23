<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\Components\GatewayBadge;
use AIArmada\FilamentCashier\CustomerPortal\BillingPanelProvider;
use AIArmada\FilamentCashier\CustomerPortal\Pages\BillingOverview;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManagePaymentMethods;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManageSubscriptions;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ViewInvoices;
use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\FilamentCashierServiceProvider;
use AIArmada\FilamentCashier\Pages\BillingDashboard;
use AIArmada\FilamentCashier\Pages\GatewayManagement;
use AIArmada\FilamentCashier\Pages\GatewaySetup;
use AIArmada\FilamentCashier\Policies\PaymentMethodPolicy;
use AIArmada\FilamentCashier\Policies\SubscriptionPolicy;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages\ListInvoices;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages\CreateSubscription;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages\ListSubscriptions;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages\ViewSubscription;
use AIArmada\FilamentCashier\Support\CurrencyFormatter;
use AIArmada\FilamentCashier\Support\UnifiedInvoice;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use AIArmada\FilamentCashier\Widgets\GatewayBreakdownWidget;
use AIArmada\FilamentCashier\Widgets\GatewayComparisonWidget;
use AIArmada\FilamentCashier\Widgets\TotalMrrWidget;
use AIArmada\FilamentCashier\Widgets\TotalSubscribersWidget;
use AIArmada\FilamentCashier\Widgets\UnifiedChurnWidget;
use Filament\Panel;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
use Mockery\MockInterface;
use Spatie\LaravelPackageTools\Package;

afterEach(function (): void {
    Auth::guard()->logout();
    Mockery::close();
});

if (! function_exists('filamentCashier_makeSchemaLivewire')) {
    function filamentCashier_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = [],
            ): Filament\Schemas\Components\Component | Filament\Actions\Action | Filament\Actions\ActionGroup | null {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

function filamentCashier_makeTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

function filamentCashier_setProperty(object $object, string $property, mixed $value): void
{
    $reflection = new ReflectionObject($object);

    while (! $reflection->hasProperty($property) && ($parent = $reflection->getParentClass())) {
        $reflection = $parent;
    }

    if (! $reflection->hasProperty($property)) {
        throw new RuntimeException("Property [{$property}] not found on " . $object::class);
    }

    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);
    $prop->setValue($object, $value);
}

it('covers the filament-cashier public surface', function (): void {
    config()->set('filament-cashier.gateways.stripe', [
        'label' => 'Stripe',
        'icon' => 'heroicon-o-credit-card',
        'color' => 'info',
        'dashboard_url' => 'https://example.test/stripe',
    ]);
    config()->set('filament-cashier.gateways.chip', [
        'label' => 'CHIP',
        'icon' => 'heroicon-o-credit-card',
        'color' => 'warning',
        'dashboard_url' => 'https://example.test/chip',
    ]);

    if (! SchemaFacade::hasTable('subscriptions')) {
        SchemaFacade::create('subscriptions', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name')->nullable();
            $table->string('stripe_id')->nullable();
            $table->string('stripe_status')->nullable();
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    if (! SchemaFacade::hasTable('subscription_items')) {
        SchemaFacade::create('subscription_items', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->string('stripe_id')->nullable();
            $table->string('stripe_product')->nullable();
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('unit_amount')->nullable();
            $table->timestamps();
        });
    }

    if (! SchemaFacade::hasTable('cashier_chip_subscriptions')) {
        SchemaFacade::create('cashier_chip_subscriptions', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id');
            $table->nullableMorphs('owner');
            $table->string('type');
            $table->string('chip_id')->unique();
            $table->string('chip_status');
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('recurring_token')->nullable();
            $table->string('billing_interval')->default('month');
            $table->integer('billing_interval_count')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    if (! SchemaFacade::hasTable('cashier_chip_subscription_items')) {
        SchemaFacade::create('cashier_chip_subscription_items', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id');
            $table->nullableMorphs('owner');
            $table->string('chip_id')->unique();
            $table->string('chip_product')->nullable();
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('unit_amount')->nullable();
            $table->timestamps();
        });
    }

    /** @var class-string<Model> $userModel */
    $userModel = config('auth.providers.users.model');

    /** @var Model $dbUser */
    $dbUser = $userModel::query()->create([
        'name' => 'Test User',
        'email' => 'cashier-test@example.com',
        'password' => bcrypt('secret'),
    ]);

    // Align owner context with the current customer/billable.
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($dbUser));

    Auth::guard()->setUser($dbUser);

    \AIArmada\CashierChip\Cashier::useCustomerModel($userModel);
    \AIArmada\CashierChip\Cashier::useSubscriptionModel(\AIArmada\CashierChip\Subscription::class);
    \AIArmada\CashierChip\Cashier::useSubscriptionItemModel(\AIArmada\CashierChip\SubscriptionItem::class);

    // Seed one CHIP subscription + item to exercise CHIP branches (no external API calls).
    $chipSubscriptionId = (string) Str::uuid();
    \AIArmada\CashierChip\Subscription::query()->create([
        'id' => $chipSubscriptionId,
        'user_id' => $dbUser->getKey(),
        'type' => 'default',
        'chip_id' => 'sub_' . $chipSubscriptionId,
        'chip_status' => \AIArmada\CashierChip\Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_basic',
        'quantity' => 1,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'next_billing_at' => now()->addDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \AIArmada\CashierChip\SubscriptionItem::query()->create([
        'id' => (string) Str::uuid(),
        'subscription_id' => $chipSubscriptionId,
        'chip_id' => 'item_' . Str::uuid(),
        'chip_product' => 'prod_basic',
        'chip_price' => 'price_basic',
        'quantity' => 1,
        'unit_amount' => 10_00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    /** @var Package&MockInterface $package */
    $package = Mockery::mock(Package::class);
    $package->shouldReceive('name')->once()->with('filament-cashier')->andReturnSelf();
    $package->shouldReceive('hasConfigFile')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('hasViews')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('hasTranslations')->once()->withNoArgs()->andReturnSelf();

    (new FilamentCashierServiceProvider(app()))->configurePackage($package);
    app()->register(FilamentCashierServiceProvider::class);

    expect(app()->bound(FilamentCashierPlugin::class))->toBeTrue();

    expect(CurrencyFormatter::getSymbol('usd'))->toBe('$');
    expect(CurrencyFormatter::getSymbol('zzz'))->toBe('ZZZ ');
    expect(CurrencyFormatter::format(12345, 'USD'))->toBe('$123.45');
    expect(CurrencyFormatter::formatWithCode(12345, 'usd'))->toBe('123.45 USD');
    expect(CurrencyFormatter::isZeroDecimal('JPY'))->toBeTrue();
    expect(CurrencyFormatter::getPrecision('JPY'))->toBe(0);
    expect(CurrencyFormatter::formatAuto(12345, 'JPY'))->toBe('¥12,345');
    expect(CurrencyFormatter::formatAuto(12345, 'USD'))->toBe('$123.45');

    $badge = new GatewayBadge('stripe');
    expect($badge->label)->toBe('Stripe');
    expect($badge->render())->toBeInstanceOf(\Illuminate\Contracts\View\View::class);

    $stripeInvoice = new class
    {
        public string $id = 'in_123';

        public string $currency = 'usd';

        public string $number = 'INV-001';

        public bool $paid = true;

        public function rawTotal(): int
        {
            return 12345;
        }

        public function date(): Carbon
        {
            return Carbon::now();
        }

        public function dueDate(): ?Carbon
        {
            return null;
        }

        public function invoicePdf(): string
        {
            return 'https://example.test/invoice.pdf';
        }

        public function asStripeInvoice(): object
        {
            return (object) [
                'status' => 'paid',
                'status_transitions' => (object) ['paid_at' => time()],
            ];
        }
    };

    $unifiedStripeInvoice = UnifiedInvoice::fromStripe($stripeInvoice, 'user_1');
    expect($unifiedStripeInvoice->formattedAmount())->toBe('$123.45');
    expect($unifiedStripeInvoice->externalDashboardUrl())->toBe('https://example.test/stripe/invoices/in_123');

    $chipInvoice = (object) [
        'id' => 'ch_1',
        'reference' => 'CHIP-REF',
        'amount' => 5000,
        'status' => 'pending',
        'created_at' => Carbon::now(),
        'paid_at' => null,
        'pdf_url' => null,
    ];

    $unifiedChipInvoice = UnifiedInvoice::fromChip($chipInvoice, 'user_2');
    expect($unifiedChipInvoice->formattedAmount())->toBe('RM50.00');
    expect($unifiedChipInvoice->externalDashboardUrl())->toBe('https://example.test/chip/purchases/ch_1');

    $stripeSubscription = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;

        public function active(): bool
        {
            return true;
        }

        public function onTrial(): bool
        {
            return false;
        }

        public function onGracePeriod(): bool
        {
            return false;
        }

        public function canceled(): bool
        {
            return false;
        }

        public function pastDue(): bool
        {
            return false;
        }

        public function items(): object
        {
            return new class
            {
                public function exists(): bool
                {
                    return false;
                }
            };
        }
    };

    $stripeSubscription->forceFill([
        'id' => 1,
        'user_id' => 1,
        'type' => 'default',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
        'created_at' => Carbon::now(),
    ]);

    $unifiedStripeSub = UnifiedSubscription::fromStripe($stripeSubscription);
    expect($unifiedStripeSub->formattedAmount())->toBe('$0.00');
    expect($unifiedStripeSub->billingCycle())->toBeString();
    expect($unifiedStripeSub->needsAttention())->toBeFalse();

    $chipSubscription = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;

        public function active(): bool
        {
            return false;
        }

        public function onTrial(): bool
        {
            return true;
        }
    };

    $chipSubscription->forceFill([
        'id' => 'sub_chip',
        'user_id' => 1,
        'type' => 'default',
        'plan_id' => 'plan_yearly',
        'amount' => 5000,
        'quantity' => 1,
        'trial_ends_at' => Carbon::now()->addDay(),
        'ends_at' => null,
        'next_billing_at' => null,
        'created_at' => Carbon::now(),
        'status' => 'trialing',
    ]);

    $unifiedChipSub = UnifiedSubscription::fromChip($chipSubscription);
    expect($unifiedChipSub->formattedAmount())->toBe('RM50.00');
    expect($unifiedChipSub->billingCycle())->toBeString();

    /** @var Panel&MockInterface $pluginPanel */
    $pluginPanel = Mockery::mock(Panel::class);
    // @phpstan-ignore method.notFound
    $pluginPanel->shouldReceive('pages')->andReturnSelf();
    // @phpstan-ignore method.notFound
    $pluginPanel->shouldReceive('resources')->andReturnSelf();
    // @phpstan-ignore method.notFound
    $pluginPanel->shouldReceive('widgets')->andReturnSelf();

    (new FilamentCashierPlugin)->register($pluginPanel);

    $billingDashboard = app(BillingDashboard::class);
    expect($billingDashboard->getTitle())->toBeString();
    expect($billingDashboard->getColumns())->toBeArray();
    expect($billingDashboard->getWidgets())->toBeArray();
    expect(BillingDashboard::getNavigationLabel())->toBeString();

    $gatewaySetup = app(GatewaySetup::class);
    expect($gatewaySetup->getTitle())->toBeString();
    expect($gatewaySetup->getGateways())->toBeArray();

    $gatewayManagement = app(GatewayManagement::class);
    expect($gatewayManagement->getTitle())->toBeString();
    expect($gatewayManagement->getGatewayHealth())->toBeInstanceOf(Collection::class);
    expect($gatewayManagement->testConnectionAction())->toBeInstanceOf(\Filament\Actions\Action::class);
    expect($gatewayManagement->setDefaultAction())->toBeInstanceOf(\Filament\Actions\Action::class);

    $checkGatewayHealth = new ReflectionMethod(GatewayManagement::class, 'checkGatewayHealth');
    $checkGatewayHealth->setAccessible(true);
    expect($checkGatewayHealth->invoke($gatewayManagement, 'unknown'))->toBeArray();

    $paymentMethodPolicy = new PaymentMethodPolicy;
    $subscriptionPolicy = new SubscriptionPolicy;

    $user = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;
    };
    $user->forceFill(['id' => 123]);

    $owned = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;
    };
    $owned->forceFill(['user_id' => 123]);

    $notOwned = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;
    };
    $notOwned->forceFill(['user_id' => 999]);

    expect($paymentMethodPolicy->viewAny($user))->toBeTrue();
    expect($paymentMethodPolicy->view($user, $owned))->toBeTrue();
    expect($paymentMethodPolicy->view($user, $notOwned))->toBeFalse();
    expect($paymentMethodPolicy->create($user))->toBeTrue();
    expect($paymentMethodPolicy->update($user, $owned))->toBeTrue();
    expect($paymentMethodPolicy->delete($user, $owned))->toBeTrue();
    expect($paymentMethodPolicy->setDefault($user, $owned))->toBeTrue();

    expect($subscriptionPolicy->viewAny($user))->toBeTrue();
    expect($subscriptionPolicy->view($user, $owned))->toBeTrue();
    expect($subscriptionPolicy->cancel($user, $owned))->toBeTrue();
    expect($subscriptionPolicy->resume($user, $owned))->toBeTrue();
    expect($subscriptionPolicy->update($user, $owned))->toBeTrue();
    expect($subscriptionPolicy->swap($user, $owned))->toBeTrue();

    expect(UnifiedSubscriptionResource::table(filamentCashier_makeTable()))->toBeInstanceOf(Table::class);
    expect(UnifiedInvoiceResource::table(filamentCashier_makeTable()))->toBeInstanceOf(Table::class);
    expect(UnifiedSubscriptionResource::getPages())->toBeArray();
    expect(UnifiedInvoiceResource::getPages())->toBeArray();

    $schemaLivewire = filamentCashier_makeSchemaLivewire();
    $createSubscription = app(CreateSubscription::class);
    expect($createSubscription->form(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);

    $listSubscriptions = app(ListSubscriptions::class);
    filamentCashier_setProperty($listSubscriptions, 'allSubscriptions', collect([$unifiedChipSub, $unifiedStripeSub]));
    filamentCashier_setProperty($listSubscriptions, 'activeTab', 'issues');
    filamentCashier_setProperty($listSubscriptions, 'tableFilters', [
        'gateway' => ['value' => 'chip'],
        'status' => ['value' => $unifiedChipSub->status->value],
    ]);
    expect($listSubscriptions->getTabs())->toBeArray();
    expect($listSubscriptions->getTableRecordKey(['gateway' => 'stripe', 'id' => 'abc']))->toBeString();
    expect($listSubscriptions->getTableRecords())->toBeInstanceOf(Collection::class);

    $listInvoices = app(ListInvoices::class);
    filamentCashier_setProperty($listInvoices, 'allInvoices', collect([$unifiedChipInvoice, $unifiedStripeInvoice]));
    filamentCashier_setProperty($listInvoices, 'activeTab', 'chip');
    filamentCashier_setProperty($listInvoices, 'tableFilters', [
        'gateway' => ['value' => 'chip'],
        'status' => ['value' => $unifiedChipInvoice->status->value],
    ]);
    expect($listInvoices->getTabs())->toBeArray();
    expect($listInvoices->getTableRecordKey(['id' => 'abc']))->toBeString();
    expect($listInvoices->getTableRecords())->toBeInstanceOf(Collection::class);

    $viewSubscription = app(ViewSubscription::class);
    $viewSubscription->mount("chip-{$chipSubscriptionId}");

    expect($viewSubscription->infolist(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);

    $headerActionsMethod = new ReflectionMethod(ViewSubscription::class, 'getHeaderActions');
    $headerActionsMethod->setAccessible(true);
    expect($headerActionsMethod->invoke($viewSubscription))->toBeArray();

    $gatewayDetailsMethod = new ReflectionMethod(ViewSubscription::class, 'getGatewayDetailsSchema');
    $gatewayDetailsMethod->setAccessible(true);
    expect($gatewayDetailsMethod->invoke($viewSubscription))->toBeArray();

    $widgets = [
        GatewayBreakdownWidget::class,
        GatewayComparisonWidget::class,
        TotalMrrWidget::class,
        TotalSubscribersWidget::class,
        UnifiedChurnWidget::class,
    ];

    foreach ($widgets as $widgetClass) {
        $widget = app($widgetClass);

        if (is_subclass_of($widgetClass, ChartWidget::class)) {
            $dataMethod = new ReflectionMethod($widgetClass, 'getData');
            $dataMethod->setAccessible(true);
            $typeMethod = new ReflectionMethod($widgetClass, 'getType');
            $typeMethod->setAccessible(true);
            $optionsMethod = new ReflectionMethod($widgetClass, 'getOptions');
            $optionsMethod->setAccessible(true);

            expect($dataMethod->invoke($widget))->toBeArray();
            expect($typeMethod->invoke($widget))->toBeString();
            expect($optionsMethod->invoke($widget))->toBeArray();
        }

        if (is_subclass_of($widgetClass, StatsOverviewWidget::class)) {
            $statsMethod = new ReflectionMethod($widgetClass, 'getStats');
            $statsMethod->setAccessible(true);
            $stats = $statsMethod->invoke($widget);

            expect($stats)->toBeArray()->and($stats)->not()->toBeEmpty();
            expect($stats[0])->toBeInstanceOf(Stat::class);
        }
    }

    $panelProvider = new BillingPanelProvider(app());

    /** @var Panel&MockInterface $billingPanel */
    $billingPanel = Mockery::mock(Panel::class);
    $billingPanel->shouldReceive('id')->once()->andReturnSelf();
    $billingPanel->shouldReceive('path')->once()->andReturnSelf();
    $billingPanel->shouldReceive('brandName')->once()->andReturnSelf();
    $billingPanel->shouldReceive('colors')->once()->andReturnSelf();
    $billingPanel->shouldReceive('login')->once()->andReturnSelf();
    $billingPanel->shouldReceive('authGuard')->once()->andReturnSelf();
    $billingPanel->shouldReceive('pages')->once()->andReturnSelf();
    $billingPanel->shouldReceive('middleware')->once()->andReturnSelf();
    $billingPanel->shouldReceive('authMiddleware')->once()->andReturnSelf();

    $panelProvider->panel($billingPanel);

    $portalPages = [
        BillingOverview::class,
        ManageSubscriptions::class,
        ManagePaymentMethods::class,
        ViewInvoices::class,
    ];

    foreach ($portalPages as $pageClass) {
        $page = app($pageClass);
        expect($page->getTitle())->toBeString();
    }

    $billingOverview = app(BillingOverview::class);
    $widgetsMethod = new ReflectionMethod(BillingOverview::class, 'getHeaderWidgets');
    $widgetsMethod->setAccessible(true);
    expect($widgetsMethod->invoke($billingOverview))->toBeArray();

    $authUser = new class extends AuthenticatableUser
    {
        protected $guarded = [];

        public function paymentMethods(): Collection
        {
            return collect([(object) ['id' => 'pm_1', 'card' => (object) ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]]]);
        }

        public function defaultPaymentMethod(): object
        {
            return (object) ['id' => 'pm_1'];
        }

        public function defaultChipPaymentMethod(): object
        {
            return (object) ['type' => 'Card', 'last4' => '1111'];
        }

        public function updateDefaultPaymentMethod(string $paymentMethodId): void {}

        public function updateDefaultChipPaymentMethod(string $paymentMethodId): void {}

        public function findPaymentMethod(string $paymentMethodId): ?object
        {
            return new class
            {
                public function delete(): void {}
            };
        }

        public function deleteChipPaymentMethod(string $paymentMethodId): void {}

        public function chipPaymentMethods(): Collection
        {
            return collect([(object) ['id' => 'chip_pm_1', 'type' => 'Card', 'last4' => '1111', 'expiry' => '12/30', 'is_default' => true]]);
        }

        public function subscriptions(): Collection
        {
            return collect();
        }

        public function chipSubscriptions(): Collection
        {
            return collect();
        }

        public function invoices(array $options = []): array
        {
            return [];
        }

        public function chipInvoices(int $limit = 3): array
        {
            return [];
        }
    };

    $authUser->forceFill(['id' => $dbUser->getKey()]);
    Auth::guard()->setUser($authUser);

    $portalPaymentMethods = app(ManagePaymentMethods::class);
    expect($portalPaymentMethods->getPaymentMethods())->toBeArray();
    $portalPaymentMethods->setDefaultPaymentMethod('stripe', 'pm_1');
    $portalPaymentMethods->setDefaultPaymentMethod('chip', 'chip_pm_1');
    $portalPaymentMethods->deletePaymentMethod('stripe', 'pm_1');
    $portalPaymentMethods->deletePaymentMethod('chip', 'chip_pm_1');

    $portalSubscriptions = app(ManageSubscriptions::class);
    expect($portalSubscriptions->getSubscriptions())->toBeInstanceOf(Collection::class);
    $portalSubscriptions->cancelSubscription('stripe', 'missing');
    $portalSubscriptions->resumeSubscription('stripe', 'missing');
    $portalSubscriptions->cancelSubscription('chip', $chipSubscriptionId);
    $portalSubscriptions->resumeSubscription('chip', $chipSubscriptionId);

    $portalInvoices = app(ViewInvoices::class);
    expect($portalInvoices->getInvoices())->toBeInstanceOf(Collection::class);

    $portalWidgets = [
        \AIArmada\FilamentCashier\CustomerPortal\Widgets\ActiveSubscriptionsWidget::class,
        \AIArmada\FilamentCashier\CustomerPortal\Widgets\PaymentMethodsPreviewWidget::class,
        \AIArmada\FilamentCashier\CustomerPortal\Widgets\RecentInvoicesWidget::class,
    ];

    foreach ($portalWidgets as $widgetClass) {
        $widget = app($widgetClass);
        $reflection = new ReflectionClass($widgetClass);

        if ($reflection->hasMethod('getSubscriptions')) {
            expect($reflection->getMethod('getSubscriptions')->invoke($widget))->toBeInstanceOf(Collection::class);
        }

        if ($reflection->hasMethod('getPaymentMethods')) {
            expect($reflection->getMethod('getPaymentMethods')->invoke($widget))->toBeArray();
        }

        if ($reflection->hasMethod('getRecentInvoices')) {
            expect($reflection->getMethod('getRecentInvoices')->invoke($widget))->toBeInstanceOf(Collection::class);
        }
    }
});
