<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Pages;

use AIArmada\Tax\Settings\TaxSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;

/**
 * Filament settings page for managing tax configuration.
 */
class ManageTaxSettings extends SettingsPage
{
    protected static string $settings = TaxSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 11;

    public static function getNavigationLabel(): string
    {
        return __('Tax Settings');
    }

    public function getTitle(): string
    {
        return __('Tax Settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('enabled')
                    ->label(__('Enable Tax Calculation'))
                    ->helperText(__('Enable or disable tax calculations globally.')),

                TextInput::make('defaultTaxRate')
                    ->label(__('Default Tax Rate'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required()
                    ->helperText(__('Default tax rate percentage (e.g., 6 for 6%).')),

                TextInput::make('defaultTaxName')
                    ->label(__('Default Tax Name'))
                    ->required()
                    ->helperText(__('Tax name displayed on invoices (e.g., SST, GST, VAT).')),

                Toggle::make('pricesIncludeTax')
                    ->label(__('Prices Include Tax'))
                    ->helperText(__('Enable if your prices already include tax.')),

                Toggle::make('taxBasedOnShippingAddress')
                    ->label(__('Tax Based on Shipping Address'))
                    ->helperText(__('Calculate tax based on shipping address (vs billing).')),

                Toggle::make('digitalGoodsTaxable')
                    ->label(__('Digital Goods Taxable'))
                    ->helperText(__('Apply tax to digital/downloadable products.')),

                Toggle::make('shippingTaxable')
                    ->label(__('Shipping Taxable'))
                    ->helperText(__('Apply tax to shipping charges.')),

                Select::make('taxIdLabel')
                    ->label(__('Tax ID Label'))
                    ->options([
                        'VAT Number' => 'VAT Number',
                        'GST Number' => 'GST Number',
                        'SST Number' => 'SST Number',
                        'Tax ID' => 'Tax ID',
                        'ABN' => 'ABN (Australia)',
                        'EIN' => 'EIN (US)',
                    ])
                    ->required()
                    ->helperText(__('Label for customer tax identification numbers.')),

                Toggle::make('validateTaxIds')
                    ->label(__('Validate Tax IDs'))
                    ->helperText(__('Validate customer tax IDs (requires integration).')),

                Toggle::make('requireExemptionCertificate')
                    ->label(__('Require Exemption Certificate'))
                    ->helperText(__('Require certificate for B2B tax exemptions.')),
            ]);
    }
}
