<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Pages;

use AIArmada\Pricing\Settings\PricingSettings;
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
 * Filament settings page for managing pricing configuration.
 */
class ManagePricingSettings extends Page
{
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string | UnitEnum | null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament-pricing::pages.manage-pricing-settings';

    public static function getNavigationLabel(): string
    {
        return __('Pricing Settings');
    }

    public function getTitle(): string
    {
        return __('Pricing Settings');
    }

    public function mount(): void
    {
        $settings = app(PricingSettings::class);

        $this->data = [
            'defaultCurrency' => isset($settings->defaultCurrency) ? $settings->defaultCurrency : 'MYR',
            'decimalPlaces' => isset($settings->decimalPlaces) ? $settings->decimalPlaces : 2,
            'roundingMode' => isset($settings->roundingMode) ? $settings->roundingMode : 'half_up',
            'pricesIncludeTax' => isset($settings->pricesIncludeTax) ? $settings->pricesIncludeTax : false,
            'minimumOrderValue' => isset($settings->minimumOrderValue) ? $settings->minimumOrderValue : 0,
            'maximumOrderValue' => isset($settings->maximumOrderValue) ? $settings->maximumOrderValue : 0,
            'promotionalPricingEnabled' => isset($settings->promotionalPricingEnabled) ? $settings->promotionalPricingEnabled : true,
            'tieredPricingEnabled' => isset($settings->tieredPricingEnabled) ? $settings->tieredPricingEnabled : true,
            'customerGroupPricingEnabled' => isset($settings->customerGroupPricingEnabled) ? $settings->customerGroupPricingEnabled : false,
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(static::getFormComponents())
            ->statePath('data');
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function getFormComponents(): array
    {
        return [
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
        ];
    }

    public function save(): void
    {
        /** @var array<string, mixed> $state */
        $state = $this->data ?? [];

        $settings = app(PricingSettings::class);

        $settings->defaultCurrency = (string) Arr::get($state, 'defaultCurrency', 'MYR');
        $settings->decimalPlaces = (int) Arr::get($state, 'decimalPlaces', 2);
        $settings->roundingMode = (string) Arr::get($state, 'roundingMode', 'half_up');
        $settings->pricesIncludeTax = (bool) Arr::get($state, 'pricesIncludeTax', false);
        $settings->minimumOrderValue = (int) Arr::get($state, 'minimumOrderValue', 0);
        $settings->maximumOrderValue = (int) Arr::get($state, 'maximumOrderValue', 0);
        $settings->promotionalPricingEnabled = (bool) Arr::get($state, 'promotionalPricingEnabled', true);
        $settings->tieredPricingEnabled = (bool) Arr::get($state, 'tieredPricingEnabled', true);
        $settings->customerGroupPricingEnabled = (bool) Arr::get($state, 'customerGroupPricingEnabled', false);

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
