<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Gateways\AbstractGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;
use Illuminate\Support\Collection;

uses(CashierTestCase::class);

/**
 * Concrete implementation for testing AbstractGateway protected methods.
 */
class TestableGateway extends AbstractGateway
{
    public function name(): string
    {
        return 'testable';
    }

    public function verifyWebhookSignature(string $payload, array $headers): bool
    {
        return true;
    }

    public function handleWebhook(array $payload, array $headers = []): mixed
    {
        return ['handled' => true];
    }

    public function subscription(BillableContract $billable, string $type, string | array $prices = []): SubscriptionBuilderContract
    {
        return Mockery::mock(SubscriptionBuilderContract::class);
    }

    public function customer(BillableContract $billable): CustomerContract
    {
        return Mockery::mock(CustomerContract::class);
    }

    public function checkout(BillableContract $billable): CheckoutBuilderContract
    {
        return Mockery::mock(CheckoutBuilderContract::class);
    }

    public function retrieveCheckout(string $sessionId): ?CheckoutContract
    {
        return null;
    }

    public function retrieveSubscription(string $subscriptionId): ?SubscriptionContract
    {
        return null;
    }

    public function retrievePayment(string $paymentId): ?PaymentContract
    {
        return null;
    }

    public function retrieveInvoice(string $invoiceId): ?InvoiceContract
    {
        return null;
    }

    public function subscriptions(BillableContract $billable): Collection
    {
        return collect();
    }

    public function invoices(BillableContract $billable, bool | array $parameters = false): Collection
    {
        return collect();
    }

    public function paymentMethods(BillableContract $billable, ?string $type = null): Collection
    {
        return collect();
    }

    public function findPaymentMethod(BillableContract $billable, string $paymentMethodId): ?PaymentMethodContract
    {
        return null;
    }

    public function defaultPaymentMethod(BillableContract $billable): ?PaymentMethodContract
    {
        return null;
    }

    public function charge(BillableContract $billable, int $amount, ?string $paymentMethod = null, array $options = []): PaymentContract
    {
        return Mockery::mock(PaymentContract::class);
    }

    public function createSetupIntent(BillableContract $billable, array $options = []): mixed
    {
        return ['client_secret' => 'test'];
    }

    public function syncCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        return Mockery::mock(CustomerContract::class);
    }

    public function refund(string $paymentId, ?int $amount = null): mixed
    {
        return ['refunded' => true];
    }

    public function client(): mixed
    {
        return null;
    }

    public function createCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        return Mockery::mock(CustomerContract::class);
    }

    public function updateCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        return Mockery::mock(CustomerContract::class);
    }

    public function customerPortalUrl(BillableContract $billable, string $returnUrl, array $options = []): string
    {
        return 'https://portal.test.com/' . $billable->customerEmail();
    }

    // Expose protected methods for testing
    public function testGetConfig(string $key, mixed $default = null): mixed
    {
        return $this->getConfig($key, $default);
    }

    public function testGetLocale(): string
    {
        return $this->getLocale();
    }

    public function testBillableModel(): string
    {
        return $this->billableModel();
    }

    public function testGatewayIdColumn(): string
    {
        return $this->gatewayIdColumn();
    }
}

describe('AbstractGateway Protected Methods', function (): void {
    beforeEach(function (): void {
        config(['cashier.models.billable' => User::class]);
        config(['app.locale' => 'en_US']);
    });

    describe('getConfig', function (): void {
        it('returns config value when present', function (): void {
            $gateway = new TestableGateway([
                'api_key' => 'test_key_xxx',
                'nested' => ['value' => 'nested_value'],
            ]);

            expect($gateway->testGetConfig('api_key'))->toBe('test_key_xxx')
                ->and($gateway->testGetConfig('nested.value'))->toBe('nested_value');
        });

        it('returns default when config key not found', function (): void {
            $gateway = new TestableGateway([]);

            expect($gateway->testGetConfig('missing_key', 'default_value'))->toBe('default_value')
                ->and($gateway->testGetConfig('another_missing'))->toBeNull();
        });
    });

    describe('getLocale', function (): void {
        it('returns configured locale', function (): void {
            $gateway = new TestableGateway(['locale' => 'de_DE']);

            expect($gateway->testGetLocale())->toBe('de_DE');
        });

        it('returns app locale as fallback', function (): void {
            config(['app.locale' => 'fr_FR']);

            $gateway = new TestableGateway([]);

            expect($gateway->testGetLocale())->toBe('fr_FR');
        });
    });

    describe('billableModel', function (): void {
        it('returns configured model from gateway config', function (): void {
            $gateway = new TestableGateway(['model' => 'App\\Models\\Customer']);

            expect($gateway->testBillableModel())->toBe('App\\Models\\Customer');
        });

        it('returns global cashier config as fallback', function (): void {
            config(['cashier.models.billable' => User::class]);

            $gateway = new TestableGateway([]);

            expect($gateway->testBillableModel())->toBe(User::class);
        });
    });

    describe('gatewayIdColumn', function (): void {
        it('returns gateway name with _id suffix', function (): void {
            $gateway = new TestableGateway([]);

            expect($gateway->testGatewayIdColumn())->toBe('testable_id');
        });
    });

    describe('findBillable', function (): void {
        it('returns null when no billable found', function (): void {
            $gateway = new TestableGateway(['model' => User::class]);

            $billable = $gateway->findBillable('cus_nonexistent');

            expect($billable)->toBeNull();
        });
    });

    describe('newSubscription', function (): void {
        it('is an alias for subscription method', function (): void {
            $gateway = new TestableGateway([]);
            $billable = Mockery::mock(BillableContract::class);

            $result1 = $gateway->newSubscription($billable, 'default', 'price_xxx');
            $result2 = $gateway->subscription($billable, 'default', 'price_xxx');

            expect($result1)->toBeInstanceOf(SubscriptionBuilderContract::class)
                ->and($result2)->toBeInstanceOf(SubscriptionBuilderContract::class);
        });
    });

    describe('findPayment', function (): void {
        it('is an alias for retrievePayment', function (): void {
            $gateway = new TestableGateway([]);

            $result = $gateway->findPayment('pi_xxx');

            expect($result)->toBeNull(); // Our test implementation returns null
        });
    });

    describe('findInvoice', function (): void {
        it('is an alias for retrieveInvoice', function (): void {
            $gateway = new TestableGateway([]);
            $billable = Mockery::mock(BillableContract::class);

            $result = $gateway->findInvoice($billable, 'in_xxx');

            expect($result)->toBeNull(); // Our test implementation returns null
        });
    });

    describe('currencyLocale', function (): void {
        it('returns currency_locale from config', function (): void {
            $gateway = new TestableGateway(['currency_locale' => 'ja_JP']);

            expect($gateway->currencyLocale())->toBe('ja_JP');
        });

        it('falls back to getLocale when currency_locale not configured', function (): void {
            $gateway = new TestableGateway(['locale' => 'pt_BR']);

            expect($gateway->currencyLocale())->toBe('pt_BR');
        });
    });

    describe('isTestMode', function (): void {
        it('returns true when test_mode is true', function (): void {
            $gateway = new TestableGateway(['test_mode' => true]);

            expect($gateway->isTestMode())->toBeTrue();
        });

        it('returns false when test_mode is false', function (): void {
            $gateway = new TestableGateway(['test_mode' => false]);

            expect($gateway->isTestMode())->toBeFalse();
        });

        it('returns false by default', function (): void {
            $gateway = new TestableGateway([]);

            expect($gateway->isTestMode())->toBeFalse();
        });

        it('converts truthy values to boolean', function (): void {
            $gateway1 = new TestableGateway(['test_mode' => 1]);
            $gateway2 = new TestableGateway(['test_mode' => 'yes']);

            expect($gateway1->isTestMode())->toBeTrue()
                ->and($gateway2->isTestMode())->toBeTrue();
        });
    });
});
