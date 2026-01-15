<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Pages;

use AIArmada\Tax\Settings\TaxSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * Filament settings page for managing tax configuration.
 */
final class ManageTaxSettings extends Page
{
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string | UnitEnum | null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 11;

    /** @var view-string */
    protected string $view = 'filament-tax::pages.manage-tax-settings';

    public static function getNavigationLabel(): string
    {
        return __('Tax Settings');
    }

    public function getTitle(): string
    {
        return __('Tax Settings');
    }

    public function mount(): void
    {
        $settings = app(TaxSettings::class);

        $this->data = [
            'enabled' => $settings->enabled ?? true,
            'defaultTaxRate' => $settings->defaultTaxRate ?? 0.0,
            'defaultTaxName' => $settings->defaultTaxName ?? 'Tax',
            'pricesIncludeTax' => $settings->pricesIncludeTax ?? false,
            'taxBasedOnShippingAddress' => $settings->taxBasedOnShippingAddress ?? true,
            'digitalGoodsTaxable' => $settings->digitalGoodsTaxable ?? true,
            'shippingTaxable' => $settings->shippingTaxable ?? false,
            'taxIdLabel' => $settings->taxIdLabel ?? 'Tax ID',
            'validateTaxIds' => $settings->validateTaxIds ?? false,
            'requireExemptionCertificate' => $settings->requireExemptionCertificate ?? false,
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $state */
        $state = $this->data ?? [];

        $settings = app(TaxSettings::class);

        $settings->enabled = (bool) Arr::get($state, 'enabled', true);
        $settings->defaultTaxRate = (float) Arr::get($state, 'defaultTaxRate', 0.0);
        $settings->defaultTaxName = (string) Arr::get($state, 'defaultTaxName', 'Tax');
        $settings->pricesIncludeTax = (bool) Arr::get($state, 'pricesIncludeTax', false);
        $settings->taxBasedOnShippingAddress = (bool) Arr::get($state, 'taxBasedOnShippingAddress', true);
        $settings->digitalGoodsTaxable = (bool) Arr::get($state, 'digitalGoodsTaxable', true);
        $settings->shippingTaxable = (bool) Arr::get($state, 'shippingTaxable', false);
        $settings->taxIdLabel = (string) Arr::get($state, 'taxIdLabel', 'Tax ID');
        $settings->validateTaxIds = (bool) Arr::get($state, 'validateTaxIds', false);
        $settings->requireExemptionCertificate = (bool) Arr::get($state, 'requireExemptionCertificate', false);

        $settings->save();

        Notification::make()
            ->title(__('Saved'))
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label(__('Save'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
        ];
    }
}
