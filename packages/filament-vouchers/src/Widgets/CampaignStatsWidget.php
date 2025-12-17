<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use Akaunting\Money\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CampaignStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $currency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));

        $campaigns = OwnerScopedQueries::scopeVoucherLike(Campaign::query());

        $activeCampaigns = (clone $campaigns)->where('status', CampaignStatus::Active->value)->count();
        $totalCampaigns = (clone $campaigns)->count();
        $draftCampaigns = (clone $campaigns)->where('status', CampaignStatus::Draft->value)->count();

        $totalBudget = (clone $campaigns)->whereNotNull('budget_cents')->sum('budget_cents');
        $totalSpent = (clone $campaigns)->sum('spent_cents');
        $totalRedemptions = (clone $campaigns)->sum('current_redemptions');

        $abTestingActive = (clone $campaigns)->where('status', CampaignStatus::Active->value)
            ->where('ab_testing_enabled', true)
            ->count();

        return [
            Stat::make('Active Campaigns', $activeCampaigns)
                ->description("{$totalCampaigns} total campaigns")
                ->descriptionIcon(Heroicon::Megaphone)
                ->color('success'),

            Stat::make('Budget Spent', (string) Money::{$currency}((int) $totalSpent))
                ->description('of ' . (string) Money::{$currency}((int) $totalBudget) . ' allocated')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color($totalBudget > 0 && ($totalSpent / $totalBudget) > 0.8 ? 'warning' : 'info'),

            Stat::make('Total Redemptions', number_format((int) $totalRedemptions))
                ->description('Across all campaigns')
                ->descriptionIcon(Heroicon::ShoppingCart)
                ->color('primary'),

            Stat::make('A/B Tests Running', $abTestingActive)
                ->description("{$draftCampaigns} campaigns in draft")
                ->descriptionIcon(Heroicon::Beaker)
                ->color($abTestingActive > 0 ? 'success' : 'gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
