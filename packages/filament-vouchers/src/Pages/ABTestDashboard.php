<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Pages;

use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use Akaunting\Money\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property-read Campaign|null $campaign
 * @property-read Collection<int, CampaignVariant> $variants
 * @property-read CampaignVariant|null $controlVariant
 * @property-read array<string, mixed> $analysisData
 */
final class ABTestDashboard extends Page
{
    public ?string $campaignId = null;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament-vouchers::pages.ab-test-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'A/B Testing';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-vouchers.navigation_group');
    }

    public function getTitle(): string | Htmlable
    {
        return 'A/B Test Dashboard';
    }

    public function mount(): void
    {
        // Default to first active A/B test campaign
        $campaigns = OwnerScopedQueries::scopeVoucherLike(Campaign::query());

        $this->campaignId = $campaigns->where('ab_testing_enabled', true)
            ->where('status', CampaignStatus::Active->value)
            ->first()?->id;
    }

    public function getCampaignProperty(): ?Campaign
    {
        if ($this->campaignId === null) {
            return null;
        }

        $campaigns = OwnerScopedQueries::scopeVoucherLike(Campaign::query());

        return $campaigns->with('variants')->find($this->campaignId);
    }

    /**
     * @return Collection<int, CampaignVariant>
     */
    public function getVariantsProperty(): Collection
    {
        return $this->campaign !== null ? $this->campaign->variants : new Collection;
    }

    public function getControlVariantProperty(): ?CampaignVariant
    {
        return $this->variants->firstWhere('is_control', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalysisDataProperty(): array
    {
        if ($this->campaign === null || $this->variants->isEmpty()) {
            return [];
        }

        $control = $this->controlVariant;
        $currency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));

        $variantData = [];
        $suggestedWinner = null;
        $highestConversion = 0.0;

        foreach ($this->variants as $variant) {
            $significance = null;
            $comparison = null;

            if ($control !== null && ! $variant->is_control) {
                $significance = $variant->calculateSignificance($control);
                $comparison = $variant->compareToVariant($control);
            }

            $variantData[$variant->variant_code] = [
                'variant' => $variant,
                'sample_size' => $variant->applications,
                'conversions' => $variant->conversions,
                'conversion_rate' => $variant->conversion_rate / 100,
                'revenue' => (string) Money::{$currency}($variant->revenue_cents),
                'discount' => (string) Money::{$currency}($variant->discount_cents),
                'aov' => $variant->average_order_value !== null
                    ? (string) Money::{$currency}((int) $variant->average_order_value)
                    : 'N/A',
                'significance' => $significance,
                'comparison' => $comparison,
            ];

            if (! $variant->is_control && $variant->conversion_rate > $highestConversion) {
                $highestConversion = $variant->conversion_rate;
                if ($significance !== null && $significance['significant']) {
                    $suggestedWinner = $variant->variant_code;
                }
            }
        }

        return [
            'variants' => $variantData,
            'suggestedWinner' => $suggestedWinner,
            'hasEnoughData' => $this->variants->every(fn (CampaignVariant $v): bool => $v->applications >= 30),
            'totalImpressions' => $this->variants->sum('impressions'),
            'totalConversions' => $this->variants->sum('conversions'),
            'totalRevenue' => (string) Money::{$currency}($this->variants->sum('revenue_cents')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selectCampaign')
                ->label('Select Campaign')
                ->icon(Heroicon::OutlinedMegaphone)
                ->form([
                    Select::make('campaign_id')
                        ->label('Campaign')
                        ->options(
                            OwnerScopedQueries::scopeVoucherLike(Campaign::query())
                                ->where('ab_testing_enabled', true)
                                ->pluck('name', 'id')
                                ->toArray()
                        )
                        ->required()
                        ->default($this->campaignId),
                ])
                ->action(function (array $data): void {
                    $this->campaignId = $data['campaign_id'];
                }),

            Action::make('declareWinner')
                ->label('Declare Winner')
                ->icon(Heroicon::OutlinedTrophy)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Declare A/B Test Winner')
                ->modalDescription('This will deactivate other variants and route all traffic to the winner.')
                ->form([
                    Select::make('winner_variant')
                        ->label('Winning Variant')
                        ->options(
                            fn (): array => $this->variants
                                ->mapWithKeys(fn (CampaignVariant $v): array => [
                                    $v->variant_code => "{$v->variant_code} - {$v->name} ({$v->conversion_rate}% conversion)",
                                ])
                                ->toArray()
                        )
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $winner = $this->variants->firstWhere('variant_code', $data['winner_variant']);

                    if ($winner !== null && $this->campaign !== null) {
                        $this->campaign->declareWinner($winner);

                        Notification::make()
                            ->title('Winner Declared')
                            ->body("Variant {$winner->variant_code} has been declared the winner.")
                            ->success()
                            ->send();
                    }
                })
                ->visible(fn (): bool => $this->campaign !== null && $this->campaign->ab_winner_variant === null),
        ];
    }
}
