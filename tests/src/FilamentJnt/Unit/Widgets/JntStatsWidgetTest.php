<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentJnt\Widgets\JntStatsWidget;
use AIArmada\Jnt\Models\JntOrder;
use Illuminate\Database\Eloquent\Model;

uses(FilamentJntTestCase::class);

final class FilamentJntTestOwnerResolver implements OwnerResolverInterface
{
    public function __construct(private ?Model $owner) {}

    public function resolve(): ?Model
    {
        return $this->owner;
    }
}

function filamentJnt_invokeProtected(object $instance, string $methodName, array $arguments = []): mixed
{
    $method = new ReflectionMethod($instance, $methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($instance, $arguments);
}

it('builds stats and respects owner scoping', function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', true);

    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@shop.test',
        'password' => bcrypt('password'),
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@shop.test',
        'password' => bcrypt('password'),
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FilamentJntTestOwnerResolver($ownerA));

    $globalDelivered = JntOrder::query()->create([
        'order_id' => 'GLOBAL-1',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-G1',
        'delivered_at' => now(),
        'has_problem' => false,
    ]);

    $ownerAInTransit = JntOrder::query()->create([
        'order_id' => 'A-1',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-A1',
        'delivered_at' => null,
        'has_problem' => false,
        'last_status_code' => '20',
    ]);
    $ownerAInTransit->assignOwner($ownerA)->save();

    $ownerAProblem = JntOrder::query()->create([
        'order_id' => 'A-2',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-A2',
        'delivered_at' => null,
        'has_problem' => true,
    ]);
    $ownerAProblem->assignOwner($ownerA)->save();

    $ownerAPending = JntOrder::query()->create([
        'order_id' => 'A-3',
        'customer_code' => 'CUST',
        'tracking_number' => null,
        'delivered_at' => null,
        'has_problem' => false,
    ]);
    $ownerAPending->assignOwner($ownerA)->save();

    $ownerAReturn = JntOrder::query()->create([
        'order_id' => 'A-4',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-A4',
        'delivered_at' => null,
        'has_problem' => false,
        'last_status_code' => '172',
    ]);
    $ownerAReturn->assignOwner($ownerA)->save();

    $ownerBInTransit = JntOrder::query()->create([
        'order_id' => 'B-1',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-B1',
        'delivered_at' => null,
        'has_problem' => false,
        'last_status_code' => '20',
    ]);
    $ownerBInTransit->assignOwner($ownerB)->save();

    $widget = app(JntStatsWidget::class);
    $stats = filamentJnt_invokeProtected($widget, 'getStats');

    expect($stats)->toHaveCount(6);
    expect(filamentJnt_invokeProtected($widget, 'getColumns'))->toBe(6);

    // Global + ownerA records only.
    $expectedTotal = 1 + 4;
    expect($globalDelivered)->not()->toBeNull();
    expect($stats[0]->getValue())->toBe($expectedTotal);
});

it('builds stats without owner scoping when owner mode is disabled', function (): void {
    config()->set('jnt.owner.enabled', false);
    config()->set('jnt.owner.include_global', false);

    JntOrder::query()->create([
        'order_id' => 'ORD-1',
        'customer_code' => 'CUST',
        'delivered_at' => null,
        'has_problem' => false,
    ]);

    JntOrder::query()->create([
        'order_id' => 'ORD-2',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-2',
        'delivered_at' => now(),
        'has_problem' => false,
    ]);

    $widget = app(JntStatsWidget::class);
    $stats = filamentJnt_invokeProtected($widget, 'getStats');

    expect($stats)->toHaveCount(6);
    expect($stats[0]->getValue())->toBe(2);
});
