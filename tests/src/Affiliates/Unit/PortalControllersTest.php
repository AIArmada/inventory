<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Http\Controllers\Portal\DashboardController;
use AIArmada\Affiliates\Http\Controllers\Portal\LinkController;
use AIArmada\Affiliates\Http\Controllers\Portal\NetworkController;
use AIArmada\Affiliates\Http\Controllers\Portal\PayoutController;
use AIArmada\Affiliates\Http\Controllers\Portal\ProfileController;
use AIArmada\Affiliates\Http\Controllers\Portal\SupportController;
use AIArmada\Affiliates\Http\Controllers\Portal\TrainingController;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// DashboardController Tests
test('DashboardController can be instantiated', function (): void {
    $controller = app(DashboardController::class);

    expect($controller)->toBeInstanceOf(DashboardController::class);
});

test('DashboardController index returns json response', function (): void {
    $controller = app(DashboardController::class);

    $affiliate = Affiliate::create([
        'code' => 'DASH001',
        'name' => 'Dashboard Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $request = Request::create('/api/affiliate/dashboard', 'GET');
    $request->attributes->set('affiliate', $affiliate);

    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('affiliate');
    expect($data)->toHaveKey('stats');
    expect($data)->toHaveKey('recent_conversions');
    expect($data)->toHaveKey('chart_data');
    expect($data['affiliate']['code'])->toBe('DASH001');
});

test('DashboardController stats returns json response', function (): void {
    $controller = app(DashboardController::class);

    $affiliate = Affiliate::create([
        'code' => 'STATS001',
        'name' => 'Stats Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $request = Request::create('/api/affiliate/stats?period=month', 'GET');
    $request->attributes->set('affiliate', $affiliate);

    $response = $controller->stats($request);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('total_conversions');
    expect($data)->toHaveKey('total_revenue_minor');
});

test('DashboardController stats with different periods', function (): void {
    $controller = app(DashboardController::class);

    $affiliate = Affiliate::create([
        'code' => 'PERIOD001',
        'name' => 'Period Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $periods = ['week', 'month', 'quarter', 'year', 'all'];

    foreach ($periods as $period) {
        $request = Request::create("/api/affiliate/stats?period={$period}", 'GET');
        $request->attributes->set('affiliate', $affiliate);

        $response = $controller->stats($request);

        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe(200);
    }
});

// LinkController Tests
test('LinkController can be instantiated', function (): void {
    $controller = app(LinkController::class);

    expect($controller)->toBeInstanceOf(LinkController::class);
});

// NetworkController Tests
test('NetworkController can be instantiated', function (): void {
    $controller = app(NetworkController::class);

    expect($controller)->toBeInstanceOf(NetworkController::class);
});

// PayoutController Tests
test('PayoutController can be instantiated', function (): void {
    $controller = app(PayoutController::class);

    expect($controller)->toBeInstanceOf(PayoutController::class);
});

// ProfileController Tests
test('ProfileController can be instantiated', function (): void {
    $controller = app(ProfileController::class);

    expect($controller)->toBeInstanceOf(ProfileController::class);
});

// SupportController Tests
test('SupportController can be instantiated', function (): void {
    $controller = app(SupportController::class);

    expect($controller)->toBeInstanceOf(SupportController::class);
});

// TrainingController Tests
test('TrainingController can be instantiated', function (): void {
    $controller = app(TrainingController::class);

    expect($controller)->toBeInstanceOf(TrainingController::class);
});
