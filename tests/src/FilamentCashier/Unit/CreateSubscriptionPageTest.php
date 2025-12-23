<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages\CreateSubscription;
use Filament\Notifications\Notification;

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('filamentCashier_invokeProtectedMethod')) {
    function filamentCashier_invokeProtectedMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}

it('builds customer options, plans, payment methods, and can create a subscription via the unified cashier facade', function (): void {
    config()->set('cashier.models.billable', ChipBillableUser::class);

    $user = ChipBillableUser::query()->create([
        'name' => 'Portal User',
        'email' => 'portal@example.com',
        'password' => bcrypt('secret'),
    ]);

    // Ensure customer options are generated within the correct owner context.
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($user));

    $page = app(CreateSubscription::class);

    $livewire = new class extends \Livewire\Component implements \Filament\Schemas\Contracts\HasSchemas
    {
        public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
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
        ): \Filament\Schemas\Components\Component | \Filament\Actions\Action | \Filament\Actions\ActionGroup | null {
            return null;
        }

        public function getSchema(string $name): ?\Filament\Schemas\Schema
        {
            return null;
        }

        public function currentlyValidatingSchema(?\Filament\Schemas\Schema $schema): void {}

        public function getDefaultTestingSchemaName(): ?string
        {
            return null;
        }
    };

    $schema = \Filament\Schemas\Schema::make($livewire);
    expect($page->form($schema))->toBeInstanceOf(\Filament\Schemas\Schema::class);

    $customerOptions = filamentCashier_invokeProtectedMethod($page, 'getCustomerOptions');
    expect($customerOptions)->toBeArray()->toHaveKey((string) $user->getKey());

    expect(filamentCashier_invokeProtectedMethod($page, 'getPlansForGateway', [null]))->toBe([]);

    config()->set('cashier.gateways.chip.plans', [
        'plan_a' => 'Plan A',
        'plan_b' => 'Plan B',
    ]);

    $plans = filamentCashier_invokeProtectedMethod($page, 'getPlansForGateway', ['chip']);
    expect($plans)->toBeArray()->toHaveKey('plan_a')->toHaveKey('plan_b');

    $paymentMethods = filamentCashier_invokeProtectedMethod($page, 'getPaymentMethodsForUser', [(string) $user->getKey(), 'chip']);
    expect($paymentMethods)->toBeArray()->toHaveKey('chip_pm_1')->toHaveKey('chip_pm_2');

    $gateway = Mockery::mock(GatewayContract::class);
    $builder = Mockery::mock(SubscriptionBuilderContract::class);
    $subscription = Mockery::mock(SubscriptionContract::class);

    Cashier::shouldReceive('gateway')->with('chip')->twice()->andReturn($gateway);

    $gateway
        ->shouldReceive('newSubscription')
        ->twice()
        ->with(Mockery::type(ChipBillableUser::class), 'default', 'plan_a')
        ->andReturn($builder);

    $builder->shouldReceive('quantity')->once()->with(3)->andReturnSelf();
    $builder->shouldReceive('trialDays')->once()->with(7)->andReturnSelf();
    $builder->shouldReceive('create')->once()->with('chip_pm_1')->andReturn($subscription);
    $builder->shouldReceive('create')->once()->withNoArgs()->andReturn($subscription);

    $created = filamentCashier_invokeProtectedMethod($page, 'handleRecordCreation', [[
        'user_id' => $user->getKey(),
        'gateway' => 'chip',
        'type' => 'default',
        'plan_id' => 'plan_a',
        'quantity' => 3,
        'has_trial' => true,
        'trial_days' => 7,
        'payment_method' => 'chip_pm_1',
    ]]);

    expect($created)->toBeInstanceOf(ChipBillableUser::class);

    $createdWithoutPaymentMethod = filamentCashier_invokeProtectedMethod($page, 'handleRecordCreation', [[
        'user_id' => $user->getKey(),
        'gateway' => 'chip',
        'type' => 'default',
        'plan_id' => 'plan_a',
        'quantity' => 1,
        'has_trial' => false,
        'trial_days' => 14,
        'payment_method' => null,
    ]]);

    expect($createdWithoutPaymentMethod)->toBeInstanceOf(ChipBillableUser::class);

    $dataProperty = new ReflectionProperty($page, 'data');
    $dataProperty->setAccessible(true);
    $dataProperty->setValue($page, ['gateway' => 'chip']);

    $notification = filamentCashier_invokeProtectedMethod($page, 'getCreatedNotification');
    expect($notification)->toBeInstanceOf(Notification::class);
});
