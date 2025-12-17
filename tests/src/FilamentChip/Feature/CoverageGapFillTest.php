<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentChip\Actions\PurchaseExporter;
use AIArmada\FilamentChip\Actions\SendInstructionExporter;
use AIArmada\FilamentChip\Concerns\InteractsWithBillable;
use AIArmada\FilamentChip\Pages\Billing\BillingDashboard;
use AIArmada\FilamentChip\Pages\Billing\Invoices;
use AIArmada\FilamentChip\Pages\Billing\PaymentMethods;
use AIArmada\FilamentChip\Pages\Billing\Subscriptions;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\CreateBankAccount;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\ListBankAccounts;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\ViewBankAccount;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ListClients;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ViewClient;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages\ListCompanyStatements;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages\ViewCompanyStatement;
use AIArmada\FilamentChip\Resources\Pages\ReadOnlyListRecords;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ListPayments;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ViewPayment;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ListPurchases;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ViewPurchase;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\Pages\ListRecurringSchedules;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\Pages\ViewRecurringSchedule;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\CreateSendInstruction;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ListSendInstructions;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ViewSendInstruction;
use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    filament()->setCurrentPanel('test');

    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Route::get('/billing/payment-methods', fn () => 'ok')->name('filament.billing.pages.payment-methods');

    config()->set('filament-chip.billing.panel_id', 'billing');
    config()->set('filament-chip.billing.billable_model', User::class);
});

it('covers exporters notification bodies and column definitions', function (): void {
    $export = new class extends Export
    {
        public function getFailedRowsCount(): int
        {
            return 2;
        }
    };

    $export->successful_rows = 10;

    expect(PurchaseExporter::getColumns())->toBeArray();
    expect(SendInstructionExporter::getColumns())->toBeArray();

    expect(PurchaseExporter::getCompletedNotificationBody($export))->toContain('10')->toContain('2');
    expect(SendInstructionExporter::getCompletedNotificationBody($export))->toContain('10')->toContain('2');
});

it('covers InteractsWithBillable helper methods', function (): void {
    $user = User::create([
        'name' => 'Billable',
        'email' => 'billable@example.com',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($user);

    $host = new class
    {
        use InteractsWithBillable;

        public function publicGetBillable(): ?\Illuminate\Database\Eloquent\Model
        {
            return $this->getBillable();
        }

        public function publicGetPaymentMethods(): \Illuminate\Support\Collection
        {
            return $this->getPaymentMethods();
        }

        public function publicGetDefaultPaymentMethod(): mixed
        {
            return $this->getDefaultPaymentMethod();
        }

        public function publicBillableHasMethod(string $method): bool
        {
            return $this->billableHasMethod($method);
        }

        public function publicGetBillingPanelId(): string
        {
            return $this->getBillingPanelId();
        }

        public function publicBillingRoute(string $name, array $parameters = []): string
        {
            return $this->billingRoute($name, $parameters);
        }
    };

    expect($host->publicGetBillable())->toBeInstanceOf(User::class);
    expect($host->publicGetPaymentMethods())->toBeEmpty();
    expect($host->publicGetDefaultPaymentMethod())->toBeNull();
    expect($host->publicBillableHasMethod('subscriptions'))->toBeFalse();
    expect($host->publicGetBillingPanelId())->toBe('billing');
    expect($host->publicBillingRoute('pages.payment-methods'))->toContain('/billing/payment-methods');
});

it('covers billing pages by stubbing the billable model methods', function (): void {
    $billable = new class extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        public function setupPaymentMethodUrl(array $params): string
        {
            return 'https://example.test/setup?' . http_build_query($params);
        }

        public function updateDefaultPaymentMethod(string $id): void {}

        public function deletePaymentMethod(string $id): void {}

        public function paymentMethods(): \Illuminate\Support\Collection
        {
            return collect([['id' => 'pm_1']]);
        }

        public function defaultPaymentMethod(): array
        {
            return ['id' => 'pm_1'];
        }

        public function invoices(bool $includePending = true): \Illuminate\Support\Collection
        {
            return collect([['id' => 'in_1', 'status' => 'paid']]);
        }

        public function findInvoice(string $id): ?object
        {
            return new class
            {
                public function download(array $data): Response
                {
                    return response('ok');
                }
            };
        }

        public function subscriptions(): object
        {
            $subscription = new class
            {
                public string $id = 'sub_1';

                public function cancel(): void {}

                public function resume(): void {}
            };

            return new class ($subscription)
            {
                public function __construct(private object $subscription) {}

                public function find(string $id): ?object
                {
                    return $id === 'sub_1' ? $this->subscription : null;
                }

                public function whereIn(string $column, array $values): self
                {
                    return $this;
                }

                public function onGracePeriod(): self
                {
                    return $this;
                }

                public function active(): self
                {
                    return $this;
                }

                public function get(): \Illuminate\Support\Collection
                {
                    return collect([$this->subscription]);
                }
            };
        }
    };

    $methods = Mockery::mock(PaymentMethods::class)->makePartial();
    $methods->shouldAllowMockingProtectedMethods();
    $methods->shouldReceive('getBillable')->andReturn($billable);

    expect($methods->getViewData())->toHaveKeys(['billable', 'paymentMethods', 'defaultPaymentMethod']);
    expect($methods->getAddPaymentMethodUrl())->toContain('success_url')->toContain('cancel_url');

    $methods->setAsDefault('pm_1');
    $methods->deletePaymentMethod('pm_1');

    $subs = Mockery::mock(Subscriptions::class)->makePartial();
    $subs->shouldAllowMockingProtectedMethods();
    $subs->shouldReceive('getBillable')->andReturn($billable);

    expect($subs->getViewData())->toHaveKeys(['billable', 'subscriptions', 'cancelledSubscriptions']);
    $subs->cancelSubscription('sub_1');
    $subs->resumeSubscription('sub_1');
    $subs->formatAmount(12345);

    $invoices = Mockery::mock(Invoices::class)->makePartial();
    $invoices->shouldAllowMockingProtectedMethods();
    $invoices->shouldReceive('getBillable')->andReturn($billable);

    expect($invoices->getViewData())->toHaveKeys(['billable', 'invoices']);
    expect($invoices->downloadInvoice('in_1'))->toBeInstanceOf(Response::class);

    $dash = Mockery::mock(BillingDashboard::class)->makePartial();
    $dash->shouldAllowMockingProtectedMethods();
    $dash->shouldReceive('getBillable')->andReturn($billable);

    expect($dash->getViewData())->toHaveKeys(['billable', 'subscriptions', 'paymentMethods', 'defaultPaymentMethod', 'invoices']);
    $dash->formatAmount(100);
});

it('covers resource pages and read-only list base class', function (): void {
    $readOnly = new class extends ReadOnlyListRecords
    {
        protected static string $resource = \AIArmada\FilamentChip\Resources\PaymentResource::class;
    };

    $m = (new ReflectionClass(ReadOnlyListRecords::class))->getMethod('getHeaderActions');
    $m->setAccessible(true);
    expect($m->invoke($readOnly))->toBeArray()->toBeEmpty();

    expect((new ListPurchases)->getTitle())->toBeString();
    expect((new ListPayments)->getTitle())->toBe('Payments');
    expect((new ListClients)->getTitle())->toBe('Clients');

    expect((new ViewClient)->getTitle())->toBe('Client Details');
    expect((new ViewPayment)->getTitle())->toBe('Payment Details');

    expect((new ListCompanyStatements)->getTitle())->toBeString();
    expect((new ListRecurringSchedules)->getTitle())->toBeString();

    $purchaseRecord = new class extends \Illuminate\Database\Eloquent\Model
    {
        public $reference = 'REF-123';

        public function getKey(): string
        {
            return 'key';
        }
    };

    $viewPurchase = new ViewPurchase;

    $recordProp = (new ReflectionClass(Filament\Resources\Pages\ViewRecord::class))->getProperty('record');
    $recordProp->setAccessible(true);
    $recordProp->setValue($viewPurchase, $purchaseRecord);

    expect($viewPurchase->getTitle())->toContain('REF-123');

    // Cover create record mutators + view record actions.
    Schema::dropIfExists('chip_bank_accounts');
    Schema::create('chip_bank_accounts', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->nullableMorphs('owner');
        $table->string('status')->nullable();
        $table->string('name')->nullable();
        $table->string('account_number')->nullable();
        $table->string('bank_code')->nullable();
        $table->string('reference')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('chip_send_instructions');
    Schema::create('chip_send_instructions', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->nullableMorphs('owner');
        $table->integer('bank_account_id')->nullable();
        $table->string('amount')->default('0');
        $table->string('email')->nullable();
        $table->string('description')->nullable();
        $table->string('reference')->nullable();
        $table->string('state')->nullable();
        $table->timestamps();
    });

    $bankAccount = BankAccount::query()->create([
        'id' => 10,
        'status' => 'pending',
        'name' => 'Acct',
        'account_number' => '123',
        'bank_code' => 'MBBEMYKL',
    ]);

    $sendInstruction = SendInstruction::query()->create([
        'id' => 20,
        'bank_account_id' => 10,
        'amount' => '1.00',
        'reference' => 'SI',
        'state' => 'completed',
    ]);

    app()->instance(ChipSendService::class, new class ($bankAccount, $sendInstruction)
    {
        public function __construct(private BankAccount $bankAccount, private SendInstruction $sendInstruction) {}

        public function createBankAccount(string $bankCode, string $accountNumber, string $accountHolderName, ?string $reference = null): BankAccount
        {
            return $this->bankAccount;
        }

        public function createSendInstruction(int $amountInCents, string $currency, string $recipientBankAccountId, string $description, string $reference, string $email): SendInstruction
        {
            return $this->sendInstruction;
        }

        public function updateBankAccount(string $id, array $data): void {}

        public function deleteBankAccount(string $id): void {}

        public function resendSendInstructionWebhook(string $id): void {}

        public function cancelSendInstruction(string $id): void {}
    });

    $createBank = new CreateBankAccount;

    $mutate = (new ReflectionClass($createBank))->getMethod('mutateFormDataBeforeCreate');
    $mutate->setAccessible(true);

    $data = $mutate->invoke($createBank, [
        'bank_code' => 'MBBEMYKL',
        'account_number' => '123',
        'name' => 'Acct',
    ]);

    expect($data)->toHaveKey('id')->toHaveKey('status');

    $createPayout = new CreateSendInstruction;

    $mutate = (new ReflectionClass($createPayout))->getMethod('mutateFormDataBeforeCreate');
    $mutate->setAccessible(true);

    $data = $mutate->invoke($createPayout, [
        'amount' => 1.23,
        'bank_account_id' => '10',
        'description' => 'Test',
        'reference' => 'ref',
        'email' => 'to@example.com',
    ]);

    expect($data)->toHaveKey('id')->toHaveKey('state');

    $viewBank = new ViewBankAccount;
    $recordProp->setValue($viewBank, $bankAccount);

    $m = (new ReflectionClass($viewBank))->getMethod('getHeaderActions');
    $m->setAccessible(true);

    foreach ($m->invoke($viewBank) as $action) {
        $action->isVisible();
    }

    $viewSend = new ViewSendInstruction;
    $recordProp->setValue($viewSend, $sendInstruction);

    $m = (new ReflectionClass($viewSend))->getMethod('getHeaderActions');
    $m->setAccessible(true);

    foreach ($m->invoke($viewSend) as $action) {
        $action->isVisible();

        if ($action->getName() === 'resend_webhook') {
            $fn = $action->getActionFunction();

            if ($fn instanceof Closure) {
                $fn();
            }
        }
    }

    // Cover list record pages' header actions.
    $listBank = new ListBankAccounts;
    $m = (new ReflectionClass($listBank))->getMethod('getHeaderActions');
    $m->setAccessible(true);
    expect($m->invoke($listBank))->toBeArray();

    $listSend = new ListSendInstructions;
    $m = (new ReflectionClass($listSend))->getMethod('getHeaderActions');
    $m->setAccessible(true);
    expect($m->invoke($listSend))->toBeArray();
});
