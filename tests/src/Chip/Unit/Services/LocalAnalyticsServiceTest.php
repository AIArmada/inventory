<?php

declare(strict_types=1);

use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Services\LocalAnalyticsService;

beforeEach(function () {
    $this->service = new LocalAnalyticsService;
});

function createPurchase(array $attributes): Purchase
{
    return Purchase::create(array_merge([
        'id' => uniqid('p-'),
        'type' => 'purchase',
        'brand_id' => 'brand-1',
        'created_on' => now()->timestamp,
        'updated_on' => now()->timestamp,
        'status' => 'paid',
        'client' => [],
        'purchase' => ['total' => 1000, 'currency' => 'MYR'],
        'issuer_details' => [],
        'transaction_data' => [],
        'status_history' => [],
    ], $attributes));
}

it('calculates revenue metrics', function () {
    $now = now();

    // Current period purchase (Paid)
    createPurchase([
        'status' => 'paid',
        'total_minor' => 10000, // 100.00
        'created_at' => $now,
    ]);

    // Current period refunded
    createPurchase([
        'status' => 'refunded',
        'total_minor' => 5000,
        'refund_amount_minor' => 5000, // 50.00
        'created_at' => $now,
    ]);

    // Previous period purchase (to test growth rate)
    createPurchase([
        'status' => 'paid',
        'total_minor' => 8000,
        'created_at' => $now->copy()->subDays(10),
    ]);

    $metrics = $this->service->getRevenueMetrics($now->copy()->subDays(5), $now->copy()->addDay());

    expect($metrics->grossRevenue)->toBe(10000);
    expect($metrics->refunds)->toBe(5000);
    expect($metrics->netRevenue)->toBe(5000);
    expect($metrics->transactionCount)->toBe(1);
    expect($metrics->averageTransaction)->toBe(10000.0);
});

it('calculates transaction metrics', function () {
    $now = now();

    createPurchase(['status' => 'paid', 'created_at' => $now]);
    createPurchase(['status' => 'failed', 'created_at' => $now]);
    createPurchase(['status' => 'pending', 'created_at' => $now]);
    createPurchase(['status' => 'refunded', 'created_at' => $now]);

    $metrics = $this->service->getTransactionMetrics($now->copy()->subDay(), $now->copy()->addDay());

    expect($metrics->total)->toBe(4);
    expect($metrics->successful)->toBe(1);
    expect($metrics->failed)->toBe(1);
    expect($metrics->pending)->toBe(1);
    expect($metrics->refunded)->toBe(1);
    expect($metrics->successRate)->toBe(25.0);
});

it('calculates payment method breakdown', function () {
    $now = now();

    createPurchase(['status' => 'paid', 'payment_method' => 'card', 'total_minor' => 1000, 'created_at' => $now]);
    createPurchase(['status' => 'paid', 'payment_method' => 'card', 'total_minor' => 2000, 'created_at' => $now]);
    createPurchase(['status' => 'paid', 'payment_method' => 'fpx', 'total_minor' => 1500, 'created_at' => $now]);
    createPurchase(['status' => 'failed', 'payment_method' => 'card', 'total_minor' => 1000, 'created_at' => $now]);

    $breakdown = $this->service->getPaymentMethodBreakdown($now->copy()->subDay(), $now->copy()->addDay());

    expect($breakdown)->toHaveCount(2);

    // Card (3 attempts, 2 success)
    $card = collect($breakdown)->firstWhere('method', 'card');
    expect($card['attempts'])->toBe(3);
    expect($card['successful'])->toBe(2);
    expect($card['revenue'])->toBe(3000);
    expect($card['success_rate'])->toBe(66.67);

    // FPX (1 attempt, 1 success)
    $fpx = collect($breakdown)->firstWhere('method', 'fpx');
    expect($fpx['attempts'])->toBe(1);
    expect($fpx['successful'])->toBe(1);
    expect($fpx['revenue'])->toBe(1500);
    expect($fpx['success_rate'])->toBe(100.0);
});

it('calculates failure analysis', function () {
    $now = now();

    createPurchase([
        'status' => 'failed',
        'failure_reason' => 'insufficient_funds',
        'total_minor' => 1000,
        'created_at' => $now,
    ]);

    createPurchase([
        'status' => 'error',
        'failure_reason' => 'insufficient_funds',
        'total_minor' => 2000,
        'created_at' => $now,
    ]);

    createPurchase([
        'status' => 'failed',
        'failure_reason' => 'timeout',
        'total_minor' => 1500,
        'created_at' => $now,
    ]);

    $analysis = $this->service->getFailureAnalysis($now->copy()->subDay(), $now->copy()->addDay());

    expect($analysis)->toHaveCount(2);

    $funds = collect($analysis)->firstWhere('reason', 'insufficient_funds');
    expect($funds['count'])->toBe(2);
    expect($funds['lost_revenue'])->toBe(3000);

    $timeout = collect($analysis)->firstWhere('reason', 'timeout');
    expect($timeout['count'])->toBe(1);
    expect($timeout['lost_revenue'])->toBe(1500);
});

it('gets dashboard metrics', function () {
    $now = now();
    createPurchase(['status' => 'paid', 'total_minor' => 1000, 'created_at' => $now]);

    $dashboard = $this->service->getDashboardMetrics($now->copy()->subDay(), $now->copy()->addDay());

    expect($dashboard->revenue)->toBeInstanceOf(\AIArmada\Chip\Data\RevenueMetrics::class);
    expect($dashboard->transactions)->toBeInstanceOf(\AIArmada\Chip\Data\TransactionMetrics::class);
    expect($dashboard->paymentMethods)->toBeArray();
    expect($dashboard->failures)->toBeArray();
});
