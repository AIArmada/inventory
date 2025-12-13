<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Pages;

use AIArmada\Pricing\Settings\PricingSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;

/**
 * Filament settings page for managing pricing configuration.
 */
class ManagePricingSettings extends SettingsPage
{
    protected static string $settings = PricingSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('Pricing Settings');
    }

    public function getTitle(): string
    {
        return __('Pricing Settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('defaultCurrency')
                    ->label(__('Default Currency'))
                    ->options([
                        'MYR' => 'Malaysian Ringgit (MYR)',
                        'USD' => 'US Dollar (USD)',
                        'EUR' => 'Euro (EUR)',
                        'GBP' => 'British Pound (GBP)',
                        'SGD' => 'Singapore Dollar (SGD)',
                        'THB' => 'Thai Baht (THB)',
                        'IDR' => 'Indonesian Rupiah (IDR)',
                        'PHP' => 'Philippine Peso (PHP)',
                    ])
                    ->required()
                    ->helperText(__('The default currency for all pricing.')),

                TextInput::make('decimalPlaces')
                    ->label(__('Decimal Places'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(4)
                    ->required()
                    ->helperText(__('Number of decimal places for price display.')),

                Select::make('roundingMode')
                    ->label(__('Rounding Mode'))
                    ->options([
                        'up' => __('Round Up'),
                        'down' => __('Round Down'),
                        'half_up' => __('Round Half Up'),
                        'half_down' => __('Round Half Down'),
                    ])
                    ->required()
                    ->helperText(__('How to round calculated prices.')),

                Toggle::make('pricesIncludeTax')
                    ->label(__('Prices Include Tax'))
                    ->helperText(__('Enable if your prices already include tax.')),

                TextInput::make('minimumOrderValue')
                    ->label(__('Minimum Order Value'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cents')
                    ->helperText(__('Minimum order value in minor units (cents).')),

                TextInput::make('maximumOrderValue')
                    ->label(__('Maximum Order Value'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cents')
                    ->helperText(__('Maximum order value in minor units (cents).')),

                Toggle::make('promotionalPricingEnabled')
                    ->label(__('Promotional Pricing'))
                    ->helperText(__('Enable promotional/sale pricing features.')),

                Toggle::make('tieredPricingEnabled')
                    ->label(__('Tiered Pricing'))
                    ->helperText(__('Enable quantity-based tiered pricing.')),

                Toggle::make('customerGroupPricingEnabled')
                    ->label(__('Customer Group Pricing'))
                    ->helperText(__('Enable different prices for customer groups.')),
            ]);
    }
}
