<?php

declare(strict_types=1);

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentChip\Actions\PurchaseExporter;
use AIArmada\FilamentChip\Actions\SendInstructionExporter;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\CreateBankAccount;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\ListBankAccounts;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\ViewBankAccount;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ListClients;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ViewClient;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages\ListCompanyStatements;
use AIArmada\FilamentChip\Resources\Pages\ReadOnlyListRecords;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ListPayments;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ViewPayment;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ListPurchases;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ViewPurchase;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\CreateSendInstruction;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ListSendInstructions;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ViewSendInstruction;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    filament()->setCurrentPanel('test');

    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });
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

    app()->instance(ChipSendService::class, new class($bankAccount, $sendInstruction)
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
