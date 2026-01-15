---
title: Usage
---

# Usage

Guide to using the Filament JNT resources, actions, and widgets.

---

## Orders Resource

The JntOrderResource provides a full-featured interface for managing shipping orders.

### List View

The orders table includes:

| Column | Description |
|--------|-------------|
| Order ID | Your order reference (copyable) |
| Tracking # | J&T tracking number (copyable) |
| Customer | Customer code |
| Type | Express type badge |
| Service | Service type badge |
| Status | Normalized status with icon |
| Problem | Problem indicator |
| Weight | Chargeable weight |
| Value | Package value |
| COD | Cash on delivery amount |
| Delivered | Delivery timestamp |
| Created | Creation timestamp |

### Filters

Available filters:

- **Status** - Filter by normalized tracking status
- **Express Type** - Domestic, Next Day, Fresh
- **Service Type** - Door to Door, Walk-In
- **Has Problem** - Show only problematic orders
- **Delivered** - Show only delivered orders
- **Pending** - Show only undelivered orders

### Search

Searchable fields:
- Order ID
- Tracking number
- Customer code
- Last status

### View Page

The order detail view displays:

- Order information (IDs, type, service)
- Sender address details
- Receiver address details
- Package information (weight, dimensions, value)
- Status and tracking history
- Request/response payloads (if enabled)

---

## Tracking Events Resource

View and filter tracking history.

### Columns

| Column | Description |
|--------|-------------|
| Order | Related order ID |
| Tracking # | Bill code |
| Scan Type | Event type (COLLECT, TRANSFER, etc.) |
| Status | Normalized status |
| Location | Scan location |
| Description | Event description |
| Timestamp | When event occurred |

### Filters

- By order
- By scan type
- By date range
- By location

---

## Webhook Logs Resource

Monitor webhook activity and troubleshoot issues.

### Columns

| Column | Description |
|--------|-------------|
| Bill Code | Tracking number |
| Order ID | Related order |
| Processed | Processing status |
| Exception | Error message if failed |
| Created | When received |

### Features

- View raw webhook payload
- Filter by processing status
- Identify failed webhooks
- Debug webhook issues

---

## Actions

### Cancel Order Action

Cancel a shipping order from the view page:

1. Click **Cancel Order** button
2. Select cancellation reason from grouped options
3. Add custom reason if "Other" selected
4. Confirm cancellation

**Reason Categories**:
- Customer-Initiated (changed mind, wrong item, etc.)
- Merchant-Initiated (out of stock, price error, etc.)
- Delivery Issues (wrong address, recipient unavailable)
- Payment Issues (payment failed, fraud suspected)
- Other (system error, custom reason)

**Visibility**: Only shown for non-delivered, non-cancelled orders.

### Sync Tracking Action

Manually sync tracking information:

1. Click **Sync Tracking** button
2. Confirm the sync action
3. Wait for API response
4. View updated status

**Features**:
- Fetches latest tracking from J&T API
- Updates order status
- Creates new tracking events
- Shows success/error notification

**Visibility**: Only shown for orders with tracking number.

---

## Dashboard Widget

The JntStatsWidget displays shipping statistics on your dashboard.

### Stats Displayed

| Stat | Description | Color |
|------|-------------|-------|
| Total Orders | All shipping orders | Primary |
| Delivered | Orders with delivery date | Success |
| In Transit | On the way | Info |
| Pending | Awaiting pickup | Warning |
| Returns | Being returned | Purple |
| Problems | Requires attention | Danger |

### Features

- **Caching**: Stats cached for 30 seconds
- **Owner-scoped**: Filtered by current tenant
- **Responsive**: 6-column layout
- **Icons**: Heroicons for visual clarity

### Widget Placement

The widget appears on the dashboard by default. To customize placement:

```php
// In your panel provider
->widgets([
    // Your custom widgets order
    AccountWidget::class,
    JntStatsWidget::class,
])
```

---

## Customization

### Extending Resources

Create custom resources by extending the base:

```php
use AIArmada\FilamentJnt\Resources\JntOrderResource;

class CustomOrderResource extends JntOrderResource
{
    // Override model if using custom model
    protected static ?string $model = CustomJntOrder::class;
    
    // Add custom columns
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                ...parent::getTableColumns(),
                TextColumn::make('custom_field'),
            ]);
    }
}
```

### Custom Actions

Add your own actions:

```php
use Filament\Actions\Action;

class CustomResource extends JntOrderResource
{
    public static function getActions(): array
    {
        return [
            ...parent::getActions(),
            Action::make('customAction')
                ->label('Custom')
                ->action(fn ($record) => /* ... */),
        ];
    }
}
```

### Custom Widget

Create a custom stats widget:

```php
use AIArmada\FilamentJnt\Widgets\JntStatsWidget;

class CustomStatsWidget extends JntStatsWidget
{
    protected function getStats(): array
    {
        $stats = parent::getStats();
        
        // Add custom stat
        $stats[] = Stat::make('Custom', $this->customCount())
            ->description('Custom metric')
            ->color('info');
        
        return $stats;
    }
}
```

---

## Testing

### Test Resources

```php
use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\Jnt\Models\JntOrder;

it('can list orders', function () {
    $orders = JntOrder::factory()->count(3)->create();
    
    livewire(ListJntOrders::class)
        ->assertCanSeeTableRecords($orders);
});

it('can view order details', function () {
    $order = JntOrder::factory()->create();
    
    livewire(ViewJntOrder::class, ['record' => $order->getKey()])
        ->assertSuccessful();
});
```

### Test Actions

```php
use AIArmada\FilamentJnt\Actions\CancelOrderAction;

it('can cancel order', function () {
    $order = JntOrder::factory()->create(['status' => 'pending']);
    
    livewire(ViewJntOrder::class, ['record' => $order->getKey()])
        ->callAction('cancelOrder', [
            'reason' => 'CUSTOMER_CHANGED_MIND',
        ])
        ->assertHasNoActionErrors();
    
    expect($order->fresh()->status)->toBe('cancelled');
});
```

### Test Widget

```php
use AIArmada\FilamentJnt\Widgets\JntStatsWidget;

it('displays order stats', function () {
    JntOrder::factory()->count(5)->create();
    JntOrder::factory()->delivered()->count(3)->create();
    
    livewire(JntStatsWidget::class)
        ->assertSee('Total Orders')
        ->assertSee('5')
        ->assertSee('Delivered')
        ->assertSee('3');
});
```

---

## Best Practices

### Performance

1. **Enable polling appropriately** - Use longer intervals for low-traffic panels
2. **Cache configuration** - Run `php artisan config:cache` in production
3. **Widget caching** - Stats are cached for 30 seconds automatically

### Security

1. **Owner scoping** - Enable for multi-tenant applications
2. **Action authorization** - Actions check authentication
3. **ID validation** - Actions validate record ownership

### UX

1. **Filters above content** - Easy access to filtering
2. **Copyable fields** - Order ID and tracking number are copyable
3. **Polling** - Tables auto-refresh for live updates
4. **Notifications** - Actions provide success/error feedback
