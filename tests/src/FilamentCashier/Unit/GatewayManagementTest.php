<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\Pages\GatewayManagement;
use Filament\Actions\Action;
use Illuminate\Support\Collection;

it('reports gateway health and default gateway without network calls', function (): void {
    // Default gateway now falls back to core cashier config.
    config()->set('cashier.default', 'chip');

    $page = app(GatewayManagement::class);

    expect(GatewayManagement::getNavigationLabel())->toBeString();
    expect(fn () => GatewayManagement::getNavigationGroup())->toThrow(LogicException::class);
    expect($page->getTitle())->toBeString();
    expect($page->getMaxContentWidth())->not->toBeNull();
    expect($page->getGatewayDetector())->not->toBeNull();

    $health = $page->getGatewayHealth();
    expect($health)->toBeInstanceOf(Collection::class);
    expect($health->first())->toHaveKeys(['gateway', 'label', 'color', 'icon', 'status', 'statusColor', 'lastCheck', 'message']);

    expect($page->getDefaultGateway())->toBe('chip');

    $testAction = $page->testConnectionAction();
    expect($testAction)->toBeInstanceOf(Action::class);
    $testActionFn = $testAction->getActionFunction();
    expect($testActionFn)->not->toBeNull();
    $testActionFn(['gateway' => 'chip']);

    $setDefaultAction = $page->setDefaultAction();
    expect($setDefaultAction)->toBeInstanceOf(Action::class);
    $setDefaultFn = $setDefaultAction->getActionFunction();
    expect($setDefaultFn)->not->toBeNull();
    $setDefaultFn(['gateway' => 'chip']);

    $reflection = new ReflectionClass(GatewayManagement::class);
    $method = $reflection->getMethod('checkGatewayHealth');
    $method->setAccessible(true);

    expect($method->invoke($page, 'unknown'))->toMatchArray([
        'status' => 'unknown',
    ]);

    expect($method->invoke($page, 'stripe'))->toMatchArray([
        'status' => 'not_configured',
    ]);

    $headerActions = $reflection->getMethod('getHeaderActions');
    $headerActions->setAccessible(true);
    expect($headerActions->invoke($page))->toBeArray();
});
