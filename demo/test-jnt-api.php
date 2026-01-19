<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Services\JntExpressService;

echo "=== JNT API Test (Using Official Sample) ===\n\n";

// Show config
echo "Environment: " . config('jnt.environment') . "\n";
echo "Customer Code: " . config('jnt.customer_code') . "\n";
echo "API Account: " . config('jnt.api_account') . "\n";
echo "Base URL: " . config('jnt.base_urls.' . config('jnt.environment')) . "\n\n";

try {
    $jnt = app(JntExpressService::class);
    
    echo "Testing JNT API connection with official sample data...\n";
    
    // Use EXACT sample data from JNT documentation
    $sender = new AddressData(
        name: 'J&T sender',
        phone: '60123456',
        address: 'No 32, Jalan Kempas 4',
        postCode: '81930',
        countryCode: 'MYS',
        state: 'Johor',
        city: 'Bandar Penawar',
        area: 'Taman Desaru Utama'
    );
    
    $receiver = new AddressData(
        name: 'J&T receiver',
        phone: '60987654',
        address: '4678, Laluan Sentang 35',
        postCode: '31000',
        countryCode: 'MYS',
        state: 'Perak',
        city: 'Batu Gajah',
        area: 'Kampung Seri Mariah'
    );
    
    $items = [
        new ItemData(
            name: 'basketball',
            quantity: 2,
            weight: 10,
            price: 50.00,
            englishName: 'basketball',
            description: 'This is a basketball',
            currency: 'USD'
        ),
        new ItemData(
            name: 'phone',
            quantity: 1,
            weight: 100,
            price: 4000.00,
            englishName: 'phone',
            description: 'This is a phone',
            currency: 'USD'
        )
    ];
    
    $packageInfo = new PackageInfoData(
        quantity: 10,
        weight: 10.0,
        value: 880.00,
        goodsType: 'ITN2',
        length: 10.0,
        width: 10.0,
        height: null
    );
    
    $result = $jnt->createOrder($sender, $receiver, $items, $packageInfo, 'YLTEST' . date('YmdHis'));
    echo "SUCCESS!\n";
    echo "Tracking Number: " . ($result->trackingNumber ?? 'N/A') . "\n";
    echo "Order ID: " . ($result->orderId ?? 'N/A') . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    if (method_exists($e, 'getApiResponse')) {
        echo "API Response: " . json_encode($e->getApiResponse(), JSON_PRETTY_PRINT) . "\n";
    }
}
