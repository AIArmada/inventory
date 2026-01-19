<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check CHIP: Is the payment real from server?
$order = \AIArmada\Orders\Models\Order::where('order_number', 'ORD-20260119-CWTCTISV')->first();

if (!$order) {
    echo "Order not found\n";
    exit(1);
}

$payment = $order->payments()->first();

echo "=== LOCAL PAYMENT RECORD ===\n";
echo "Transaction ID: " . $payment->transaction_id . "\n";
echo "Gateway: " . $payment->gateway . "\n";
echo "Status: " . $payment->status->value . "\n";

// Verify with CHIP API
try {
    $chip = app(\AIArmada\Chip\Services\ChipCollectService::class);
    $purchase = $chip->getPurchase($payment->transaction_id);
    
    echo "\n=== FROM CHIP SERVER (API) ===\n";
    echo "Purchase ID: " . $purchase->id . "\n";
    echo "Status: " . $purchase->status . "\n";
    echo "Total: " . $purchase->getAmount()->format() . "\n";
    echo "Currency: " . $purchase->getCurrency() . "\n";
    echo "Client Email: " . $purchase->client->email . "\n";
    echo "Created At: " . $purchase->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
    echo "Is Test: " . ($purchase->is_test ? 'Yes (Sandbox)' : 'No (Production)') . "\n";
    echo "Reference: " . ($purchase->reference ?? 'N/A') . "\n";
    echo "\n✅ CHIP TRANSACTION IS VERIFIED FROM SERVER!\n";
} catch (\Exception $e) {
    echo "\nAPI Error: " . $e->getMessage() . "\n";
}
