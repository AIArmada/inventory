<?php

declare(strict_types=1);

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\Client;
use AIArmada\Chip\Models\CompanyStatement;
use AIArmada\Chip\Models\DailyMetric;
use AIArmada\Chip\Models\Payment;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Models\SendLimit;
use AIArmada\Chip\Models\SendWebhook;
use AIArmada\Chip\Models\Webhook;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

describe('ChipModel base class', function (): void {
    it('uses HasUuids trait', function (): void {
        $purchase = new Purchase;
        expect(in_array(HasUuids::class, class_uses_recursive($purchase)))->toBeTrue();
    });

    it('returns correct table name with prefix', function (): void {
        config(['chip.database.table_prefix' => 'chip_']);

        $purchase = new Purchase;
        expect($purchase->getTable())->toBe('chip_purchases');

        $client = new Client;
        expect($client->getTable())->toBe('chip_clients');

        $webhook = new Webhook;
        expect($webhook->getTable())->toBe('chip_webhooks');
    });

    it('uses guarded property', function (): void {
        $purchase = new Purchase;
        expect($purchase->getGuarded())->toBe([]);
    });

    it('has owner relationship', function (): void {
        $purchase = new Purchase;
        expect($purchase->owner())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphTo::class);
    });

    it('can check if model has owner', function (): void {
        $purchase = new Purchase;
        expect($purchase->hasOwner())->toBeFalse();
        expect($purchase->isGlobal())->toBeTrue();

        $purchase->forceFill([
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-123',
        ]);
        expect($purchase->hasOwner())->toBeTrue();
        expect($purchase->isGlobal())->toBeFalse();
    });

    it('can assign owner to model', function (): void {
        $purchase = new Purchase;
        $mockOwner = new class extends Model {
            protected $guarded = [];

            public function getKey(): string|int|null
            {
                return 'owner-123';
            }

            public function getMorphClass(): string
            {
                return 'TestOwner';
            }
        };

        $purchase->assignOwner($mockOwner);

        expect($purchase->owner_type)->toBe('TestOwner')
            ->and($purchase->owner_id)->toBe('owner-123');
    });

    it('can remove owner from model', function (): void {
        $purchase = new Purchase;
        $purchase->forceFill([
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-123',
        ]);

        $purchase->removeOwner();

        expect($purchase->owner_type)->toBeNull()
            ->and($purchase->owner_id)->toBeNull();
    });

    it('returns audit include fields', function (): void {
        $purchase = new Purchase;
        $auditFields = $purchase->getAuditInclude();

        expect($auditFields)->toBeArray()
            ->and($auditFields)->toContain('status');
    });
});

describe('ChipIntegerModel base class', function (): void {
    it('uses integer primary key', function (): void {
        $bankAccount = new BankAccount;
        expect($bankAccount->getKeyType())->toBe('int')
            ->and($bankAccount->getIncrementing())->toBeFalse();
    });

    it('returns correct table name with prefix for integer models', function (): void {
        config(['chip.database.table_prefix' => 'chip_']);

        $bankAccount = new BankAccount;
        expect($bankAccount->getTable())->toBe('chip_bank_accounts');

        $sendInstruction = new SendInstruction;
        expect($sendInstruction->getTable())->toBe('chip_send_instructions');

        $sendLimit = new SendLimit;
        expect($sendLimit->getTable())->toBe('chip_send_limits');

        $sendWebhook = new SendWebhook;
        expect($sendWebhook->getTable())->toBe('chip_send_webhooks');
    });
});

describe('Purchase model', function (): void {
    it('has fillable attributes', function (): void {
        $purchase = new Purchase;
        expect($purchase->getGuarded())->toBe([]);
    });

    it('has payments relationship', function (): void {
        $purchase = new Purchase;
        expect($purchase->payments())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('can access amount attribute', function (): void {
        $purchase = new Purchase;
        $purchase->forceFill([
            'purchase' => ['amount' => 10000, 'currency' => 'MYR', 'total' => 10000],
        ]);

        expect($purchase->amount)->toBe(10000);
        expect($purchase->currency)->toBe('MYR');
    });

    it('can access client email attribute', function (): void {
        $purchase = new Purchase;
        $purchase->forceFill([
            'client' => ['email' => 'test@example.com'],
        ]);

        expect($purchase->clientEmail)->toBe('test@example.com');
    });

    it('can get status color', function (): void {
        $purchase = new Purchase;
        $purchase->forceFill(['status' => 'paid']);
        expect($purchase->statusColor())->toBe('success');

        $purchase->forceFill(['status' => 'processing']);
        expect($purchase->statusColor())->toBe('warning');

        $purchase->forceFill(['status' => 'failed']);
        expect($purchase->statusColor())->toBe('danger');

        $purchase->forceFill(['status' => 'unknown']);
        expect($purchase->statusColor())->toBe('secondary');
    });

    it('can get status badge', function (): void {
        $purchase = new Purchase;
        $purchase->forceFill(['status' => 'pending_capture']);

        expect($purchase->statusBadge())->toBe('Pending Capture');
    });

    it('can access total money', function (): void {
        $purchase = new Purchase;
        $purchase->forceFill([
            'purchase' => ['total' => 15000, 'currency' => 'MYR'],
        ]);

        expect($purchase->totalMoney)->toBeInstanceOf(Akaunting\Money\Money::class);
    });

    it('can get formatted total', function (): void {
        $purchase = new Purchase;
        $purchase->forceFill([
            'purchase' => ['total' => 15000, 'currency' => 'MYR'],
        ]);

        expect($purchase->formattedTotal)->toBeString();
    });

    it('can build timeline from status history', function (): void {
        $purchase = new Purchase;
        $purchase->forceFill([
            'status_history' => [
                ['status' => 'created', 'timestamp' => time() - 3600],
                ['status' => 'paid', 'timestamp' => time()],
            ],
        ]);

        $timeline = $purchase->timeline;
        expect($timeline)->toBeArray()
            ->and(count($timeline))->toBe(2)
            ->and($timeline[0]['status'])->toBe('created')
            ->and($timeline[1]['status'])->toBe('paid');
    });
});

describe('Client model', function (): void {
    it('returns correct table name', function (): void {
        config(['chip.database.table_prefix' => 'chip_']);
        $client = new Client;
        expect($client->getTable())->toBe('chip_clients');
    });

    it('can access location attribute', function (): void {
        $client = new Client;
        $client->forceFill([
            'city' => 'Kuala Lumpur',
            'state' => 'WP',
            'country' => 'Malaysia',
        ]);

        expect($client->location)->toBe('Kuala Lumpur, WP, Malaysia');
    });

    it('returns null for empty location', function (): void {
        $client = new Client;
        expect($client->location)->toBeNull();
    });

    it('can access shipping location attribute', function (): void {
        $client = new Client;
        $client->forceFill([
            'shipping_city' => 'Penang',
            'shipping_state' => 'Penang',
            'shipping_country' => 'Malaysia',
        ]);

        expect($client->shippingLocation)->toBe('Penang, Penang, Malaysia');
    });
});

describe('Payment model', function (): void {
    it('has purchase relationship', function (): void {
        $payment = new Payment;
        expect($payment->purchase())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });

    it('can access money attributes', function (): void {
        $payment = new Payment;
        $payment->forceFill([
            'amount' => 10000,
            'net_amount' => 9700,
            'fee_amount' => 300,
            'pending_amount' => 0,
            'currency' => 'MYR',
        ]);

        expect($payment->amountMoney)->toBeInstanceOf(Akaunting\Money\Money::class);
        expect($payment->netAmountMoney)->toBeInstanceOf(Akaunting\Money\Money::class);
        expect($payment->feeAmountMoney)->toBeInstanceOf(Akaunting\Money\Money::class);
        expect($payment->pendingAmountMoney)->toBeInstanceOf(Akaunting\Money\Money::class);
    });

    it('can get formatted amounts', function (): void {
        $payment = new Payment;
        $payment->forceFill([
            'amount' => 10000,
            'net_amount' => 9700,
            'fee_amount' => 300,
            'pending_amount' => 0,
            'currency' => 'MYR',
        ]);

        expect($payment->formattedAmount)->toBeString();
        expect($payment->formattedNetAmount)->toBeString();
        expect($payment->formattedFeeAmount)->toBeString();
        expect($payment->formattedPendingAmount)->toBeString();
    });

    it('can access timestamp attributes', function (): void {
        $payment = new Payment;
        $payment->forceFill([
            'paid_on' => time(),
            'remote_paid_on' => time(),
            'created_on' => time(),
            'updated_on' => time(),
            'currency' => 'MYR',
        ]);

        expect($payment->paidOn)->toBeInstanceOf(Carbon\CarbonImmutable::class);
        expect($payment->remotePaidOn)->toBeInstanceOf(Carbon\CarbonImmutable::class);
        expect($payment->createdOn)->toBeInstanceOf(Carbon\CarbonImmutable::class);
        expect($payment->updatedOn)->toBeInstanceOf(Carbon\CarbonImmutable::class);
    });
});

describe('Webhook model', function (): void {
    it('returns correct table name', function (): void {
        config(['chip.database.table_prefix' => 'chip_']);
        $webhook = new Webhook;
        expect($webhook->getTable())->toBe('chip_webhooks');
    });

    it('can access timestamp attributes', function (): void {
        $webhook = new Webhook;
        $webhook->forceFill([
            'created_on' => time(),
            'updated_on' => time(),
        ]);

        expect($webhook->createdOn)->toBeInstanceOf(Carbon\CarbonImmutable::class);
        expect($webhook->updatedOn)->toBeInstanceOf(Carbon\CarbonImmutable::class);
    });

    it('has correct casts', function (): void {
        $webhook = new Webhook;
        $casts = $webhook->getCasts();

        expect($casts['events'])->toBe('array');
        expect($casts['payload'])->toBe('array');
        expect($casts['headers'])->toBe('array');
        expect($casts['verified'])->toBe('boolean');
        expect($casts['processed'])->toBe('boolean');
    });
});

describe('DailyMetric model', function (): void {
    it('returns correct table name', function (): void {
        config(['chip.database.table_prefix' => 'chip_']);
        $metric = new DailyMetric;
        expect($metric->getTable())->toBe('chip_daily_metrics');
    });
});

describe('CompanyStatement model', function (): void {
    it('returns correct table name', function (): void {
        config(['chip.database.table_prefix' => 'chip_']);
        $statement = new CompanyStatement;
        expect($statement->getTable())->toBe('chip_company_statements');
    });
});

describe('BankAccount model', function (): void {
    it('returns correct table name', function (): void {
        config(['chip.database.table_prefix' => 'chip_']);
        $bankAccount = new BankAccount;
        expect($bankAccount->getTable())->toBe('chip_bank_accounts');
    });
});
