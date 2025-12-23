<?php

declare(strict_types=1);

use AIArmada\Chip\Models\Purchase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages\ListInvoices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

if (! function_exists('filamentCashier_setProtectedProperty')) {
    function filamentCashier_setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionObject($object);

        while (! $reflection->hasProperty($property) && ($parent = $reflection->getParentClass())) {
            $reflection = $parent;
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}

it('lists CHIP purchases as unified invoices and applies tabs and filters', function (): void {
    config()->set('cashier.models.billable', User::class);

    $user = User::query()->create([
        'name' => 'Invoice User',
        'email' => 'invoice-user@example.com',
        'password' => bcrypt('secret'),
    ]);

    Auth::guard()->setUser($user);

    $purchaseA = Purchase::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'purchase',
        'created_on' => Carbon::parse('2025-01-01 00:00:00')->timestamp,
        'updated_on' => Carbon::parse('2025-01-01 00:00:00')->timestamp,
        'client' => ['email' => $user->email],
        'purchase' => ['amount' => 1000, 'currency' => 'MYR', 'total' => 1000],
        'brand_id' => (string) Str::uuid(),
        'company_id' => null,
        'user_id' => (string) $user->getKey(),
        'billing_template_id' => null,
        'client_id' => null,
        'payment' => null,
        'issuer_details' => [],
        'transaction_data' => [],
        'status_history' => [],
        'status' => 'paid',
        'viewed_on' => null,
        'send_receipt' => false,
        'is_test' => true,
        'is_recurring_token' => false,
        'recurring_token' => null,
        'skip_capture' => false,
        'force_recurring' => false,
        'reference' => 'INV-0001',
        'reference_generated' => null,
        'notes' => null,
        'issued' => null,
        'due' => null,
        'refund_availability' => 'all',
        'refundable_amount' => 0,
        'currency_conversion' => null,
        'payment_method_whitelist' => null,
        'success_redirect' => null,
        'failure_redirect' => null,
        'cancel_redirect' => null,
        'success_callback' => null,
        'invoice_url' => null,
        'checkout_url' => null,
        'direct_post_url' => null,
        'creator_agent' => null,
        'platform' => 'api',
        'product' => 'purchases',
        'created_from_ip' => null,
        'marked_as_paid' => true,
        'order_id' => null,
        'metadata' => null,
        'created_at' => Carbon::parse('2025-01-01 00:00:00'),
        'updated_at' => Carbon::parse('2025-01-01 00:00:00'),
    ]);

    $purchaseB = Purchase::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'purchase',
        'created_on' => Carbon::parse('2025-01-02 00:00:00')->timestamp,
        'updated_on' => Carbon::parse('2025-01-02 00:00:00')->timestamp,
        'client' => ['email' => $user->email],
        'purchase' => ['amount' => 2000, 'currency' => 'MYR', 'total' => 2000],
        'brand_id' => (string) Str::uuid(),
        'company_id' => null,
        'user_id' => (string) $user->getKey(),
        'billing_template_id' => null,
        'client_id' => null,
        'payment' => null,
        'issuer_details' => [],
        'transaction_data' => [],
        'status_history' => [],
        'status' => 'open',
        'viewed_on' => null,
        'send_receipt' => false,
        'is_test' => true,
        'is_recurring_token' => false,
        'recurring_token' => null,
        'skip_capture' => false,
        'force_recurring' => false,
        'reference' => 'INV-0002',
        'reference_generated' => null,
        'notes' => null,
        'issued' => null,
        'due' => null,
        'refund_availability' => 'all',
        'refundable_amount' => 0,
        'currency_conversion' => null,
        'payment_method_whitelist' => null,
        'success_redirect' => null,
        'failure_redirect' => null,
        'cancel_redirect' => null,
        'success_callback' => null,
        'invoice_url' => null,
        'checkout_url' => null,
        'direct_post_url' => null,
        'creator_agent' => null,
        'platform' => 'api',
        'product' => 'purchases',
        'created_from_ip' => null,
        'marked_as_paid' => false,
        'order_id' => null,
        'metadata' => null,
        'created_at' => Carbon::parse('2025-01-02 00:00:00'),
        'updated_at' => Carbon::parse('2025-01-02 00:00:00'),
    ]);

    $page = app(ListInvoices::class);

    $tabs = $page->getTabs();
    expect($tabs)->toHaveKeys(['all', 'chip']);

    $records = $page->getTableRecords();
    expect($records)->toHaveCount(2);
    expect($page->getTableRecordKey($records->first()))->toContain('chip-');

    filamentCashier_setProtectedProperty($page, 'activeTab', 'chip');
    $chipOnly = $page->getTableRecords();
    expect($chipOnly)->toHaveCount(2);

    filamentCashier_setProtectedProperty($page, 'activeTab', 'all');
    filamentCashier_setProtectedProperty($page, 'tableFilters', [
        'status' => ['value' => 'paid'],
    ]);

    $filtered = $page->getTableRecords();
    expect($filtered)->toHaveCount(1);
    expect((string) $filtered->first()->id)->toBe((string) $purchaseA->getKey());

    filamentCashier_setProtectedProperty($page, 'tableFilters', [
        'gateway' => ['value' => 'chip'],
    ]);

    $gatewayFiltered = $page->getTableRecords();
    expect($gatewayFiltered)->toHaveCount(2);
    expect($gatewayFiltered->pluck('id')->all())->toContain((string) $purchaseA->getKey(), (string) $purchaseB->getKey());
});

it('returns no invoices when the configured billable model does not exist', function (): void {
    config()->set('cashier.models.billable', 'App\\Models\\User');

    $page = app(ListInvoices::class);
    expect($page->getTableRecords())->toHaveCount(0);
});
