<?php

declare(strict_types=1);

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\CompanyStatement;
use AIArmada\Chip\Models\Payment;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Models\RecurringCharge;
use AIArmada\Chip\Models\RecurringSchedule;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\Chip\Services\LocalAnalyticsService;
use AIArmada\Chip\Webhooks\WebhookMonitor;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage;
use AIArmada\FilamentChip\Pages\BulkPayoutPage;
use AIArmada\FilamentChip\Pages\FinancialOverviewPage;
use AIArmada\FilamentChip\Pages\PayoutDashboardPage;
use AIArmada\FilamentChip\Pages\RefundCenterPage;
use AIArmada\FilamentChip\Pages\WebhookConfigPage;
use AIArmada\FilamentChip\Pages\WebhookMonitorPage;
use AIArmada\FilamentChip\Resources\BankAccountResource;
use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\CompanyStatementResource;
use AIArmada\FilamentChip\Resources\PaymentResource;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource;
use AIArmada\FilamentChip\Resources\SendInstructionResource;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Schemas\SendInstructionForm;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Tables\SendInstructionTable;
use AIArmada\FilamentChip\Resources\PurchaseResource\Tables\PurchaseTable;
use AIArmada\FilamentChip\Widgets\AccountBalanceWidget;
use AIArmada\FilamentChip\Widgets\AccountTurnoverWidget;
use AIArmada\FilamentChip\Widgets\BankAccountStatusWidget;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use AIArmada\FilamentChip\Widgets\PayoutAmountWidget;
use AIArmada\FilamentChip\Widgets\PayoutStatsWidget;
use AIArmada\FilamentChip\Widgets\PaymentMethodsWidget;
use AIArmada\FilamentChip\Widgets\RecentPayoutsWidget;
use AIArmada\FilamentChip\Widgets\RecentTransactionsWidget;
use AIArmada\FilamentChip\Widgets\RecurringStatsWidget;
use AIArmada\FilamentChip\Widgets\RevenueChartWidget;
use AIArmada\FilamentChip\Widgets\TokenStatsWidget;
use Filament\Forms\Form;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component as LivewireComponent;

function filamentChip_tableLivewire(): TableWidget
{
    return new class extends TableWidget
    {
        public function table(Table $table): Table
        {
            return $table;
        }
    };
}

function filamentChip_makeSchemaLivewire(): LivewireComponent & HasSchemas
{
    return new class extends LivewireComponent implements HasSchemas
    {
        public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
        {
            return null;
        }

        public function getOldSchemaState(string $statePath): mixed
        {
            return null;
        }

        public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): \Filament\Schemas\Components\Component | \Filament\Actions\Action | \Filament\Actions\ActionGroup | null
        {
            return null;
        }

        public function getSchema(string $name): ?FilamentSchema
        {
            return null;
        }

        public function currentlyValidatingSchema(?FilamentSchema $schema): void {}

        public function getDefaultTestingSchemaName(): ?string
        {
            return null;
        }
    };
}

function filamentChip_createChipTables(): void
{
    foreach ([
        'chip_purchases',
        'chip_payments',
        'chip_clients',
        'chip_webhooks',
        'chip_bank_accounts',
        'chip_send_instructions',
        'chip_company_statements',
        'chip_recurring_schedules',
        'chip_recurring_charges',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('chip_purchases', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('reference')->nullable();
        $table->string('status')->nullable();
        $table->boolean('is_test')->default(false);
        $table->integer('created_on')->nullable();
        $table->integer('due')->nullable();
        $table->integer('viewed_on')->nullable();
        $table->string('client_id')->nullable();
        $table->string('payment_method')->nullable();
        $table->json('purchase')->nullable();
        $table->json('client')->nullable();
        $table->json('payment')->nullable();
        $table->json('transaction_data')->nullable();
        $table->json('issuer_details')->nullable();
        $table->json('status_history')->nullable();
        $table->json('currency_conversion')->nullable();
        $table->json('payment_method_whitelist')->nullable();
        $table->json('metadata')->nullable();
        $table->boolean('send_receipt')->default(false);
        $table->boolean('marked_as_paid')->default(false);
    });

    Schema::create('chip_payments', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->uuid('purchase_id');
        $table->string('type')->nullable();
        $table->string('status')->nullable();
        $table->integer('amount')->default(0);
        $table->string('currency')->nullable();
        $table->integer('net_amount')->default(0);
        $table->integer('fee_amount')->default(0);
        $table->integer('pending_amount')->default(0);
        $table->boolean('is_outgoing')->default(false);
        $table->integer('paid_on')->nullable();
        $table->integer('created_on')->nullable();
        $table->integer('updated_on')->nullable();
    });

    Schema::create('chip_clients', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('email')->nullable();
        $table->string('full_name')->nullable();
        $table->string('phone')->nullable();
        $table->string('street_address')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip_code')->nullable();
        $table->string('country')->nullable();
        $table->string('shipping_street_address')->nullable();
        $table->string('shipping_city')->nullable();
        $table->string('shipping_country')->nullable();
        $table->string('legal_name')->nullable();
        $table->string('brand_name')->nullable();
        $table->string('registration_number')->nullable();
        $table->string('tax_number')->nullable();
        $table->integer('created_on')->nullable();
        $table->integer('updated_on')->nullable();
        $table->timestamps();
    });

    Schema::create('chip_webhooks', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('event')->nullable();
        $table->string('status')->default('pending');
        $table->integer('retry_count')->default(0);
        $table->float('processing_time_ms')->nullable();
        $table->string('last_error')->nullable();
        $table->string('ip_address')->nullable();
        $table->timestamps();
    });

    Schema::create('chip_bank_accounts', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('status')->nullable();
        $table->string('account_number')->nullable();
        $table->string('bank_code')->nullable();
        $table->string('name')->nullable();
        $table->boolean('is_debiting_account')->default(false);
        $table->boolean('is_crediting_account')->default(false);
        $table->timestamps();
    });

    Schema::create('chip_send_instructions', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->integer('bank_account_id')->nullable();
        $table->string('amount')->default('0');
        $table->string('email')->nullable();
        $table->string('description')->nullable();
        $table->string('reference')->nullable();
        $table->string('state')->nullable();
        $table->string('receipt_url')->nullable();
        $table->string('slug')->nullable();
        $table->timestamps();
    });

    Schema::create('chip_company_statements', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('status')->nullable();
        $table->boolean('is_test')->default(false);
        $table->integer('began_on')->nullable();
        $table->integer('finished_on')->nullable();
        $table->integer('created_on')->nullable();
        $table->integer('updated_on')->nullable();
        $table->timestamps();
    });

    Schema::create('chip_recurring_schedules', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('chip_client_id')->nullable();
        $table->string('recurring_token_id')->nullable();
        $table->string('subscriber_type')->nullable();
        $table->string('subscriber_id')->nullable();
        $table->string('status')->nullable();
        $table->integer('amount_minor')->default(0);
        $table->string('currency')->default('MYR');
        $table->string('interval')->default('month');
        $table->integer('interval_count')->default(1);
        $table->dateTime('next_charge_at')->nullable();
        $table->dateTime('last_charged_at')->nullable();
        $table->integer('failure_count')->default(0);
        $table->integer('max_failures')->default(3);
        $table->dateTime('cancelled_at')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('chip_recurring_charges', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('schedule_id');
        $table->uuid('chip_purchase_id')->nullable();
        $table->integer('amount_minor')->default(0);
        $table->string('currency')->default('MYR');
        $table->string('status')->nullable();
        $table->string('failure_reason')->nullable();
        $table->dateTime('attempted_at')->nullable();
        $table->timestamps();
    });
}

beforeEach(function (): void {
    filament()->setCurrentPanel('test');

    config()->set('chip.database.table_prefix', 'chip_');
    config()->set('chip.owner.enabled', false);

    filamentChip_createChipTables();
});

it('configures resources, tables, forms and infolists without error', function (): void {
    $tableLivewire = filamentChip_tableLivewire();
    $schemaLivewire = filamentChip_makeSchemaLivewire();

    foreach ([
        PurchaseResource::class,
        PaymentResource::class,
        ClientResource::class,
        SendInstructionResource::class,
        BankAccountResource::class,
        CompanyStatementResource::class,
        RecurringScheduleResource::class,
    ] as $resource) {
        expect($resource::getPages())->toBeArray();

        $resource::table(Table::make($tableLivewire));
        $resource::infolist(FilamentSchema::make($schemaLivewire));
    }

    BankAccountResource::form(FilamentSchema::make($schemaLivewire));
    SendInstructionResource::form(FilamentSchema::make($schemaLivewire));
    SendInstructionForm::configure(FilamentSchema::make($schemaLivewire));

    expect(true)->toBeTrue();
});

it('executes complex PurchaseTable filter logic (sqlite json_extract path)', function (): void {
    Purchase::create([
        'status' => 'paid',
        'is_test' => false,
        'purchase' => ['total' => 600000, 'currency' => 'MYR'],
    ]);

    $table = PurchaseTable::configure(Table::make(filamentChip_tableLivewire()));

    $highValue = $table->getFilter('high_value');
    expect($highValue)->not->toBeNull();

    $query = Purchase::query();
    $highValue->apply($query, ['isActive' => true]);

    expect($query->count())->toBe(1);
});

it('executes SendInstructionTable action closures using a stub ChipSendService', function (): void {
    BankAccount::query()->create([
        'id' => 1,
        'status' => 'active',
        'name' => 'Test Account',
    ]);

    $record = SendInstruction::query()->create([
        'id' => 100,
        'bank_account_id' => 1,
        'amount' => '12.34',
        'email' => 'payout@example.com',
        'description' => 'Test',
        'reference' => 'REF-1',
        'state' => 'received',
    ]);

    app()->instance(ChipSendService::class, new class
    {
        public function cancelSendInstruction(string $id): void {}

        public function resendSendInstructionWebhook(string $id): void {}
    });

    $table = SendInstructionTable::configure(Table::make(filamentChip_tableLivewire()));

    $cancel = $table->getAction('cancel');
    $resend = $table->getAction('resend_webhook');

    expect($cancel)->not->toBeNull();
    expect($resend)->not->toBeNull();

    $cancelFn = $cancel->getActionFunction();
    $resendFn = $resend->getActionFunction();

    $cancelFn($record);
    $resendFn($record);

    expect(true)->toBeTrue();
});

it('covers key pages by executing header action closures and service calls', function (): void {
    app()->instance(LocalAnalyticsService::class, new class
    {
        public function getDashboardMetrics($start, $end): DashboardMetrics
        {
            return new DashboardMetrics(
                new RevenueMetrics(10000, 0, 10000, 1, 10000, 5.0, 'MYR'),
                new TransactionMetrics(1, 1, 0, 0, 0, 100.0),
                [],
                [],
            );
        }

        public function getRevenueTrend($start, $end, string $period): array
        {
            return [['period' => 'today', 'count' => 1, 'revenue' => 10000]];
        }
    });

    $analytics = new AnalyticsDashboardPage;
    $analytics->mount();

    $actions = (new ReflectionClass($analytics))->getMethod('getHeaderActions');
    $actions->setAccessible(true);

    foreach ($actions->invoke($analytics) as $action) {
        $fn = $action->getActionFunction();

        if ($fn instanceof Closure) {
            $fn();
        }
    }

    app()->instance(ChipCollectService::class, new class
    {
        public function getAccountBalance(): array
        {
            return ['available_balance' => 1000, 'pending_balance' => 2000, 'reserved_balance' => 300];
        }

        public function getAccountTurnover(array $params): array
        {
            return ['total_income' => 10000, 'total_fees' => 500, 'total_refunds' => 250];
        }

        public function listWebhooks(): array
        {
            return ['data' => [['id' => 'wh_1']]];
        }

        public function deleteWebhook(string $id): void {}
    });

    $financial = new FinancialOverviewPage;
    $financial->mount();

    $webhookConfig = new WebhookConfigPage;
    $webhookConfig->mount();
    $webhookConfig->deleteWebhook('wh_1');

    app()->instance(ChipSendService::class, new class
    {
        public function listSendInstructions(): array
        {
            return [];
        }
    });

    BankAccount::query()->create(['id' => 2, 'status' => 'active']);
    SendInstruction::query()->create(['id' => 200, 'bank_account_id' => 2, 'amount' => '1.00', 'state' => 'processed']);

    $payout = new PayoutDashboardPage;
    $payout->mount();

    $payoutActions = (new ReflectionClass($payout))->getMethod('getHeaderActions');
    $payoutActions->setAccessible(true);

    foreach ($payoutActions->invoke($payout) as $action) {
        $fn = $action->getActionFunction();

        if ($fn instanceof Closure) {
            $fn();
        }
    }

    $refund = new RefundCenterPage;
    $refund->table(Table::make($refund));

    $bulk = new BulkPayoutPage;

    $mockForm = Mockery::mock(Form::class);
    $mockForm->shouldReceive('schema')->once()->andReturnSelf();
    $mockForm->shouldReceive('statePath')->once()->andReturnSelf();

    $bulk->form($mockForm);

    expect(true)->toBeTrue();
});

it('covers webhook monitor page actions and table configuration', function (): void {
    app()->instance(WebhookMonitor::class, new class
    {
        public function getHealth(): ?\AIArmada\Chip\Data\WebhookHealth
        {
            return null;
        }

        public function getEventDistribution(): array
        {
            return ['event' => 1];
        }

        public function getFailureBreakdown(): array
        {
            return ['failed' => 1];
        }
    });

    app()->instance(WebhookRetryManager::class, new class
    {
        public function getRetryableWebhooks()
        {
            return collect([Webhook::query()->create(['event' => 'x', 'status' => 'failed'])]);
        }

        public function retry($webhook)
        {
            return new class
            {
                public function isSuccess(): bool
                {
                    return true;
                }
            };
        }
    });

    $page = new WebhookMonitorPage;
    $page->mount();

    $table = $page->table(Table::make($page));
    expect($table->getColumns())->toHaveCount(8);

    $actions = (new ReflectionClass($page))->getMethod('getHeaderActions');
    $actions->setAccessible(true);

    foreach ($actions->invoke($page) as $action) {
        $fn = $action->getActionFunction();

        if ($fn instanceof Closure) {
            $fn();
        }
    }

    expect(true)->toBeTrue();
});

it('covers key widgets (stats + charts) with both success and failure paths', function (): void {
    app()->instance(ChipCollectService::class, new class
    {
        public function getAccountBalance(): array
        {
            return ['available' => 1000, 'pending' => 2000, 'reserved' => 300];
        }

        public function getAccountTurnover(array $params): array
        {
            return ['revenue' => 10000, 'fees' => 500];
        }
    });

    $statsWidgets = [
        new AccountBalanceWidget,
        new ChipStatsWidget,
        new PaymentMethodsWidget,
        new PayoutStatsWidget,
        new RecurringStatsWidget,
        new TokenStatsWidget,
    ];

    CompanyStatement::query()->create(['status' => 'completed', 'is_test' => false]);

    RecurringSchedule::query()->create([
        'status' => 'active',
        'amount_minor' => 1000,
        'currency' => 'MYR',
        'interval' => 'monthly',
        'interval_count' => 1,
        'next_charge_at' => now()->subDay(),
    ]);

    RecurringCharge::query()->create([
        'schedule_id' => RecurringSchedule::query()->first()->getKey(),
        'amount_minor' => 1000,
        'currency' => 'MYR',
        'status' => 'success',
        'attempted_at' => now(),
    ]);

    Purchase::query()->create([
        'status' => 'paid',
        'is_test' => false,
        'purchase' => ['amount' => 12345, 'recurring_token' => 'tok_1'],
        'payment' => ['payment_type' => 'fpx'],
        'created_on' => now()->timestamp,
    ]);

    SendInstruction::query()->create([
        'id' => 300,
        'bank_account_id' => 1,
        'amount' => '10.00',
        'state' => 'processed',
        'created_at' => now(),
    ]);

    foreach ($statsWidgets as $widget) {
        $ref = new ReflectionClass($widget);
        $method = $ref->getMethod('getStats');
        $method->setAccessible(true);
        $method->invoke($widget);
    }

    $charts = [
        new RevenueChartWidget,
        new AccountTurnoverWidget,
        new PayoutAmountWidget,
        new BankAccountStatusWidget,
    ];

    foreach ($charts as $chart) {
        $ref = new ReflectionClass($chart);
        $method = $ref->getMethod('getData');
        $method->setAccessible(true);
        $method->invoke($chart);
    }

    $tables = [
        new RecentPayoutsWidget,
        new RecentTransactionsWidget,
    ];

    foreach ($tables as $widget) {
        $widget->table(Table::make($widget));
    }

    // Cover error branch in AccountBalanceWidget + AccountTurnoverWidget.
    app()->instance(ChipCollectService::class, new class
    {
        public function getAccountBalance(): array
        {
            throw new RuntimeException('fail');
        }

        public function getAccountTurnover(array $params): array
        {
            throw new RuntimeException('fail');
        }
    });

    $errBalance = new AccountBalanceWidget;
    $errTurnover = new AccountTurnoverWidget;

    $ref = new ReflectionClass($errBalance);
    $m = $ref->getMethod('getStats');
    $m->setAccessible(true);
    $m->invoke($errBalance);

    $ref = new ReflectionClass($errTurnover);
    $m = $ref->getMethod('getData');
    $m->setAccessible(true);
    $m->invoke($errTurnover);

    expect(true)->toBeTrue();
});
