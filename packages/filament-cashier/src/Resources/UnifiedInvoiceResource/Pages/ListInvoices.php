<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages;

use AIArmada\Chip\Models\Purchase;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\UnifiedInvoice;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

final class ListInvoices extends ListRecords
{
    protected static string $resource = UnifiedInvoiceResource::class;

    /**
     * @var Collection<int, UnifiedInvoice>|null
     */
    protected ?Collection $allInvoices = null;

    public function getTabs(): array
    {
        $detector = app(GatewayDetector::class);
        $gateways = $detector->availableGateways();

        $tabs = [
            'all' => Tab::make(__('filament-cashier::subscriptions.tabs.all'))
                ->badge(fn () => $this->getAllInvoices()->count()),
        ];

        foreach ($gateways as $gateway) {
            $tabs[$gateway] = Tab::make($detector->getLabel($gateway))
                ->badge(fn () => $this->getAllInvoices()->where('gateway', $gateway)->count())
                ->badgeColor($detector->getColor($gateway))
                ->icon($detector->getIcon($gateway));
        }

        return $tabs;
    }

    /**
     * Override to use collection-based records instead of Eloquent.
     */
    public function getTableRecords(): Collection | Paginator | CursorPaginator
    {
        return $this->getFilteredInvoices();
    }

    /**
     * Get table record key.
     */
    public function getTableRecordKey(Model | array | UnifiedInvoice $record): string
    {
        if ($record instanceof UnifiedInvoice) {
            return $record->gateway . '-' . $record->id;
        }

        if ($record instanceof Model) {
            return (string) $record->getKey();
        }

        return (string) ($record['id'] ?? '');
    }

    /**
     * Get all invoices across all gateways.
     *
     * @return Collection<int, UnifiedInvoice>
     */
    protected function getAllInvoices(): Collection
    {
        if ($this->allInvoices !== null) {
            return $this->allInvoices;
        }

        $userId = auth()->id();

        if ($userId === null) {
            $this->allInvoices = collect();

            return $this->allInvoices;
        }

        $invoices = collect();
        $detector = app(GatewayDetector::class);
        $billableModel = config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel)) {
            $this->allInvoices = $invoices;

            return $invoices;
        }

        $users = CashierOwnerScope::apply($billableModel::query())
            ->whereKey($userId)
            ->limit(1)
            ->get();

        // Collect Stripe invoices
        if ($detector->isAvailable('stripe')) {
            foreach ($users as $user) {
                if (method_exists($user, 'invoices')) {
                    try {
                        $stripeInvoices = $user->invoices(['limit' => 50]);
                        foreach ($stripeInvoices as $invoice) {
                            // @phpstan-ignore method.nonObject
                            $invoices->push(UnifiedInvoice::fromStripe($invoice, (string) $user->getKey()));
                        }
                    } catch (Throwable) {
                        // Silently fail if API is not configured
                    }
                }
            }
        }

        // Collect CHIP invoices/purchases
        if ($detector->isAvailable('chip') && class_exists(Purchase::class)) {
            $chipPurchases = CashierOwnerScope::apply(Purchase::query())
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();

            foreach ($chipPurchases as $purchase) {
                $invoices->push(UnifiedInvoice::fromChip($purchase, (string) ($purchase->user_id ?? '')));
            }
        }

        $this->allInvoices = $invoices->sortByDesc('date')->values();

        return $this->allInvoices;
    }

    /**
     * Filter invoices based on active tab.
     *
     * @return Collection<int, UnifiedInvoice>
     */
    protected function getFilteredInvoices(): Collection
    {
        $invoices = $this->getAllInvoices();
        $activeTab = $this->activeTab;

        if ($activeTab && $activeTab !== 'all') {
            $invoices = $invoices->where('gateway', $activeTab);
        }

        // Apply filters from filter form
        $filterData = $this->tableFilters ?? [];

        if (isset($filterData['gateway']['value']) && $filterData['gateway']['value']) {
            $invoices = $invoices->where('gateway', $filterData['gateway']['value']);
        }

        if (isset($filterData['status']['value']) && $filterData['status']['value']) {
            $invoices = $invoices->filter(
                fn (UnifiedInvoice $inv) => $inv->status->value === $filterData['status']['value']
            );
        }

        return $invoices->values();
    }
}
