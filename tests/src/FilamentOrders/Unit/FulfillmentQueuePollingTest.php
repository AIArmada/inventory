<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentOrders\Pages\FulfillmentQueue;
use Filament\Tables\Table;

uses(TestCase::class);

it('disables fulfillment queue table polling when not visible', function (): void {
    $page = app(FulfillmentQueue::class);

    $page->isTableVisible = false;

    $table = Table::make($page);
    $configured = $page->table($table);

    expect($configured->getPollingInterval())->toBeNull();

    $page->isTableVisible = true;

    $tableVisible = Table::make($page);
    $configuredVisible = $page->table($tableVisible);

    expect($configuredVisible->getPollingInterval())->toBe('30s');
});
