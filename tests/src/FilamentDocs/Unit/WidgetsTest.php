<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget;
use AIArmada\FilamentDocs\Widgets\RevenueChartWidget;
use AIArmada\FilamentDocs\Widgets\StatusBreakdownWidget;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class);

function filamentDocs_invokeMethod(object $instance, string $methodName, array $arguments = []): mixed
{
    $method = new ReflectionMethod($instance, $methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($instance, $arguments);
}

it('builds DocStatsWidget stats and formatting', function (): void {
    Doc::factory()->count(2)->create(['status' => DocStatus::DRAFT]);
    Doc::factory()->create(['status' => DocStatus::PAID, 'total' => 100, 'paid_at' => now()]);

    $widget = app(DocStatsWidget::class);

    $stats = filamentDocs_invokeMethod($widget, 'getStats');

    expect($stats)->toHaveCount(5);
    expect(filamentDocs_invokeMethod($widget, 'getColumns'))->toBe(5);
});

it('builds RecentDocumentsWidget table', function (): void {
    $widget = app(RecentDocumentsWidget::class);

    $table = $widget->table(Table::make($widget));

    expect($table->getColumns())->not()->toBeEmpty();
    expect($widget->getTableHeading())->toBe('Recent Documents');
});

it('builds RevenueChartWidget data, options, and type', function (): void {
    Doc::factory()->create([
        'status' => DocStatus::PAID,
        'paid_at' => now(),
        'total' => 100,
    ]);

    $widget = app(RevenueChartWidget::class);
    $data = filamentDocs_invokeMethod($widget, 'getData');

    expect($data['labels'])->toHaveCount(30);
    expect($data['datasets'][0]['data'])->toHaveCount(30);
    expect(filamentDocs_invokeMethod($widget, 'getType'))->toBe('line');
    expect(filamentDocs_invokeMethod($widget, 'getOptions'))->toBeArray();
});

it('builds StatusBreakdownWidget data and color mapping', function (): void {
    Doc::factory()->create(['status' => DocStatus::DRAFT]);
    Doc::factory()->create(['status' => DocStatus::PAID]);

    $widget = app(StatusBreakdownWidget::class);
    $data = filamentDocs_invokeMethod($widget, 'getData');

    expect($data['labels'])->not()->toBeEmpty();
    expect(filamentDocs_invokeMethod($widget, 'getType'))->toBe('doughnut');
    expect(filamentDocs_invokeMethod($widget, 'getOptions'))->toBeArray();
    expect(filamentDocs_invokeMethod($widget, 'getColorHex', ['unknown']))->toBe('#6b7280');
});

it('prevents cross-tenant metric leakage in Filament Docs widgets', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $migration = require __DIR__ . '/../../../../packages/docs/database/migrations/2000_06_01_000003_add_owner_columns_to_docs_tables.php';
    $migration->up();

    OwnerContext::clearOverride();

    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    $createDocForOwner = static function (Model $owner, array $overrides = []): Doc {
        return Doc::factory()->create(array_merge([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ], $overrides));
    };

    // Owner A data
    $createDocForOwner($ownerA, ['status' => DocStatus::DRAFT]);
    $createDocForOwner($ownerA, ['status' => DocStatus::DRAFT]);
    $createDocForOwner($ownerA, ['status' => DocStatus::PAID, 'paid_at' => now(), 'total' => 100]);
    $createDocForOwner($ownerA, ['status' => DocStatus::OVERDUE, 'total' => 50]);

    // Owner B data (must never be included when scoped to Owner A)
    $createDocForOwner($ownerB, ['status' => DocStatus::DRAFT]);
    $createDocForOwner($ownerB, ['status' => DocStatus::PAID, 'paid_at' => now(), 'total' => 999]);
    $createDocForOwner($ownerB, ['status' => DocStatus::CANCELLED]);

    OwnerContext::withOwner($ownerA, function (): void {
        $statsWidget = app(DocStatsWidget::class);
        $stats = filamentDocs_invokeMethod($statsWidget, 'getStats');

        expect($stats)->toHaveCount(5);
        expect($stats[0]->getValue())->toBe(4);
        expect($stats[1]->getValue())->toBe(2);
        expect($stats[2]->getValue())->toBe(0);
        expect($stats[3]->getValue())->toBe(1);
        expect($stats[4]->getValue())->toBe(1);

        // Revenue must be owner-scoped (paid total = 100; overdue outstanding = 50)
        expect($stats[3]->getDescription())->toBe('MYR 100.00');
        expect($stats[4]->getDescription())->toBe('MYR 50.00 outstanding');

        $statusWidget = app(StatusBreakdownWidget::class);
        $statusData = filamentDocs_invokeMethod($statusWidget, 'getData');

        expect($statusData['labels'])->toContain(DocStatus::DRAFT->label());
        expect($statusData['labels'])->toContain(DocStatus::PAID->label());
        expect($statusData['labels'])->toContain(DocStatus::OVERDUE->label());
        expect($statusData['labels'])->not()->toContain(DocStatus::CANCELLED->label());

        $revenueWidget = app(RevenueChartWidget::class);
        $revenueData = filamentDocs_invokeMethod($revenueWidget, 'getData');

        expect($revenueData['labels'])->toHaveCount(30);
        expect($revenueData['datasets'][0]['data'])->toHaveCount(30);
        expect((float) end($revenueData['datasets'][0]['data']))->toBe(100.0);
    });

    OwnerContext::clearOverride();
});
