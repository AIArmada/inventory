<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Data\BankAccountData;
use AIArmada\Chip\Data\ClientData;
use AIArmada\Chip\Data\SendInstructionData;
use AIArmada\Chip\Data\SendLimitData;
use AIArmada\Chip\Data\SendWebhookData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Models\Client;
use AIArmada\Chip\Services\ChipSendService;

describe('ChipSendService', function (): void {
    beforeEach(function (): void {
        $this->client = Mockery::mock(ChipSendClient::class);
        $this->service = new ChipSendService($this->client);
    });

    it('lists accounts', function (): void {
        $data = [['id' => 'account-1']];
        $this->client->shouldReceive('get')->with('send/accounts')->once()->andReturn($data);

        expect($this->service->listAccounts())->toBe($data);
    });

    it('creates send instruction', function (): void {
        $responseData = [
            'id' => 1,
            'amount' => 100.00,
            'currency' => 'MYR',
            'bank_account_id' => 1,
            'description' => 'Test',
            'reference' => 'REF123',
            'email' => 'test@example.com',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('post')
            ->once()
            ->with('send/send_instructions', Mockery::on(function ($data) {
                return $data['amount'] === '100.00' &&
                    $data['currency'] === 'MYR' &&
                    $data['email'] === 'test@example.com';
            }))
            ->andReturn($responseData);

        $result = $this->service->createSendInstruction(
            10000,
            'MYR',
            'bank-1',
            'Test',
            'REF123',
            'test@example.com'
        );

        expect($result)->toBeInstanceOf(SendInstructionData::class);
    });

    it('validates send instruction parameters', function (): void {
        $this->service->createSendInstruction(
            -100, // Invalid amount
            'MYR',
            'bank-1',
            'Test',
            'REF123',
            'test@example.com'
        );
    })->throws(ChipValidationException::class);

    it('validates email in send instruction', function (): void {
        $this->service->createSendInstruction(
            10000,
            'MYR',
            'bank-1',
            'Test',
            'REF123',
            'invalid-email'
        );
    })->throws(ChipValidationException::class);

    it('gets send instruction', function (): void {
        $responseData = [
            'id' => 1,
            'amount' => 100.00,
            'currency' => 'MYR',
            'bank_account_id' => 1,
            'description' => 'Test',
            'reference' => 'REF123',
            'email' => 'test@example.com',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('get')->with('send/send_instructions/instruction-1')->once()->andReturn($responseData);

        $result = $this->service->getSendInstruction('instruction-1');
        expect($result)->toBeInstanceOf(SendInstructionData::class);
    });

    it('lists send instructions', function (): void {
        $data = ['data' => []];
        $this->client->shouldReceive('get')->with('send/send_instructions')->once()->andReturn($data);

        expect($this->service->listSendInstructions())->toBe($data);
    });

    it('gets send limit', function (): void {
        $responseData = [
            'id' => 1,
            'currency' => 'MYR',
            'fee_type' => 'transaction',
            'transaction_type' => 'transfer',
            'amount' => 10000,
            'fee' => 100,
            'net_amount' => 9900,
            'status' => 'active',
            'approvals_required' => 1,
            'approvals_received' => 0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('get')->with('send/send_limits/limit-1')->once()->andReturn($responseData);

        $result = $this->service->getSendLimit('limit-1');
        expect($result)->toBeInstanceOf(SendLimitData::class);
    });

    it('creates bank account', function (): void {
        $responseData = [
            'id' => 1,
            'account_number' => '1234567890',
            'bank_code' => 'MAYBANK',
            'name' => 'John Doe',
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'is_debiting_account' => false,
            'is_crediting_account' => true,
        ];

        $this->client->shouldReceive('post')
            ->once()
            ->with('send/bank_accounts', Mockery::hasKey('bank_code'))
            ->andReturn($responseData);

        $result = $this->service->createBankAccount('MAYBANK', '1234567890', 'John Doe');

        expect($result)->toBeInstanceOf(BankAccountData::class);
    });

    it('gets bank account', function (): void {
        $responseData = [
            'id' => 1,
            'account_number' => '1234567890',
            'bank_code' => 'MAYBANK',
            'name' => 'John Doe',
            'status' => 'active',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'is_debiting_account' => false,
            'is_crediting_account' => true,
        ];

        $this->client->shouldReceive('get')->with('send/bank_accounts/bank-1')->once()->andReturn($responseData);

        $result = $this->service->getBankAccount('bank-1');
        expect($result)->toBeInstanceOf(BankAccountData::class);
    });

    it('updates bank account', function (): void {
        $responseData = [
            'id' => 1,
            'account_number' => '1234567890',
            'bank_code' => 'MAYBANK',
            'name' => 'John Doe Updated',
            'status' => 'active',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'is_debiting_account' => false,
            'is_crediting_account' => true,
        ];

        $this->client->shouldReceive('put')->with('send/bank_accounts/bank-1', ['name' => 'John Doe Updated'])->once()->andReturn($responseData);

        $result = $this->service->updateBankAccount('bank-1', ['name' => 'John Doe Updated']);
        expect($result)->toBeInstanceOf(BankAccountData::class);
    });

    it('deletes bank account', function (): void {
        $this->client->shouldReceive('delete')->with('send/bank_accounts/bank-1')->once()->andReturn([]);

        $this->service->deleteBankAccount('bank-1');
    });

    it('lists bank accounts', function (): void {
        $data = ['data' => []];
        $this->client->shouldReceive('get')->with('send/bank_accounts')->once()->andReturn($data);

        expect($this->service->listBankAccounts())->toBe($data);
    });

    it('cancels send instruction', function (): void {
        $responseData = [
            'id' => 1,
            'state' => 'cancelled',
            'amount' => 100.00,
            'currency' => 'MYR',
            'bank_account_id' => 1,
            'description' => 'Test',
            'reference' => 'REF123',
            'email' => 'test@example.com',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('post')->with('send/send_instructions/instruction-1/cancel')->once()->andReturn($responseData);

        $result = $this->service->cancelSendInstruction('instruction-1');
        expect($result)->toBeInstanceOf(SendInstructionData::class);
    });

    it('manages groups', function (): void {
        $data = ['id' => 'group-1', 'name' => 'Test Group'];

        $this->client->shouldReceive('post')->with('send/groups', $data)->once()->andReturn($data);
        expect($this->service->createGroup($data))->toBe($data);

        $this->client->shouldReceive('get')->with('send/groups/group-1')->once()->andReturn($data);
        expect($this->service->getGroup('group-1'))->toBe($data);

        $this->client->shouldReceive('put')->with('send/groups/group-1', ['name' => 'Updated'])->once()->andReturn(['id' => 'group-1', 'name' => 'Updated']);
        expect($this->service->updateGroup('group-1', ['name' => 'Updated']))->toBe(['id' => 'group-1', 'name' => 'Updated']);

        $this->client->shouldReceive('delete')->with('send/groups/group-1')->once();
        $this->service->deleteGroup('group-1');

        $this->client->shouldReceive('get')->with('send/groups')->once()->andReturn([$data]);
        expect($this->service->listGroups())->toBe([$data]);
    });

    it('manages send webhooks', function (): void {
        $webhookData = [
            'id' => 1,
            'name' => 'Webhook 1',
            'public_key' => 'pk_123',
            'callback_url' => 'https://example.com/webhook',
            'email' => 'test@example.com',
            'event_hooks' => ['payment.success'],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->client->shouldReceive('post')->with('send/webhooks', Mockery::type('array'))->once()->andReturn($webhookData);
        expect($this->service->createSendWebhook(['url' => 'https://example.com/webhook']))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('get')->with('send/webhooks/webhook-1')->once()->andReturn($webhookData);
        expect($this->service->getSendWebhook('webhook-1'))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('put')->with('send/webhooks/webhook-1', Mockery::type('array'))->once()->andReturn($webhookData);
        expect($this->service->updateSendWebhook('webhook-1', ['url' => 'https://new.com']))->toBeInstanceOf(SendWebhookData::class);

        $this->client->shouldReceive('delete')->with('send/webhooks/webhook-1')->once();
        $this->service->deleteSendWebhook('webhook-1');

        $this->client->shouldReceive('get')->with('send/webhooks')->once()->andReturn([$webhookData]);
        $list = $this->service->listSendWebhooks();
        expect($list[0])->toBeInstanceOf(SendWebhookData::class);
    });

    it('deletes send instruction and resends webhooks', function (): void {
        $this->client->shouldReceive('delete')->with('send/send_instructions/instruction-1')->once();
        $this->service->deleteSendInstruction('instruction-1');

        $this->client->shouldReceive('post')->with('send/send_instructions/instruction-1/resend_webhook')->once()->andReturn(['success' => true]);
        expect($this->service->resendSendInstructionWebhook('instruction-1'))->toBe(['success' => true]);

        $this->client->shouldReceive('post')->with('send/bank_accounts/bank-1/resend_webhook')->once()->andReturn(['success' => true]);
        expect($this->service->resendBankAccountWebhook('bank-1'))->toBe(['success' => true]);
    });
});

describe('ClientData', function (): void {
    it('creates from array', function (): void {
        $data = ClientData::from([
            'id' => 'client-1',
            'email' => 'client@example.com',
            'phone' => '+1234567890',
            'full_name' => 'John Doe',
            'country' => 'MY',
            'default_currency' => 'MYR',
            'bank_account' => null,
            'personal_code' => '123123',
            'legal_name' => 'John Doe Legal',
            'brand_id' => 'brand-1',
            'cc' => [],
            'bcc' => [],
            'metadata' => [],
            'notes' => 'Test note',
            'created_on' => time(),
            'updated_on' => time(),
        ]);

        expect($data->id)->toBe('client-1');
        expect($data->email)->toBe('client@example.com');
        expect($data->fullName)->toBe('John Doe');
    });
});

describe('Client Model', function (): void {
    it('is unguarded', function (): void {
        $client = new Client;
        expect($client->getGuarded())->toBe([]);
    });

    it('casts attributes correctly', function (): void {
        $client = new Client([
            'cc' => ['test@example.com'],
            'bcc' => ['admin@example.com'],
        ]);

        expect($client->cc)->toBe(['test@example.com']);
        expect($client->bcc)->toBe(['admin@example.com']);
    });
});

describe('BankAccount Model', function (): void {
    it('returns status color and label', function (): void {
        $account = new \AIArmada\Chip\Models\BankAccount(['status' => 'active']);
        expect($account->statusColor())->toBe('success');
        expect($account->statusLabel())->toBe('Active');

        $account->status = 'pending';
        expect($account->statusColor())->toBe('warning');

        $account->status = 'rejected';
        expect($account->statusColor())->toBe('danger');
    });

    it('checks if active', function (): void {
        $account = new \AIArmada\Chip\Models\BankAccount(['status' => 'active']);
        expect($account->isActive)->toBeTrue();

        $account->status = 'pending';
        expect($account->isActive)->toBeFalse();
    });

    it('has correct table name', function (): void {
        Config::set('chip.database.table_prefix', 'chip_');
        $account = new \AIArmada\Chip\Models\BankAccount;
        expect($account->getTable())->toBe('chip_bank_accounts');
    });
});

describe('SendInstruction Model', function (): void {
    it('returns amount numeric', function (): void {
        $instruction = new \AIArmada\Chip\Models\SendInstruction(['amount' => '100.50']);
        expect($instruction->amountNumeric)->toBe(100.50);
    });

    it('returns state label and color', function (): void {
        $instruction = new \AIArmada\Chip\Models\SendInstruction(['state' => 'completed']);
        expect($instruction->stateLabel)->toBe('Completed');
        expect($instruction->stateColor())->toBe('success');

        $instruction->state = 'received';
        expect($instruction->stateColor())->toBe('warning');

        $instruction->state = 'failed';
        expect($instruction->stateColor())->toBe('danger');
    });

    it('has correct table name', function (): void {
        $instruction = new \AIArmada\Chip\Models\SendInstruction;
        expect($instruction->getTable())->toBe('chip_send_instructions');
    });
});

describe('SendLimit Model', function (): void {
    it('converts amounts to Money objects', function (): void {
        $limit = new \AIArmada\Chip\Models\SendLimit([
            'amount' => 10000,
            'net_amount' => 9900,
            'fee' => 100,
            'currency' => 'MYR',
        ]);

        expect($limit->amountMoney)->toBeInstanceOf(\Akaunting\Money\Money::class);
        expect($limit->amountMoney->getAmount())->toBe(10000);

        expect($limit->netAmountMoney)->toBeInstanceOf(\Akaunting\Money\Money::class);
        expect($limit->feeMoney)->toBeInstanceOf(\Akaunting\Money\Money::class);
    });

    it('returns status color', function (): void {
        $limit = new \AIArmada\Chip\Models\SendLimit(['status' => 'active']);
        expect($limit->statusColor())->toBe('success');

        $limit->status = 'pending';
        expect($limit->statusColor())->toBe('warning');

        $limit->status = 'blocked';
        expect($limit->statusColor())->toBe('danger');
    });
});
