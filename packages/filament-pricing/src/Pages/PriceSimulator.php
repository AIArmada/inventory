<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Pages;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Pricing\Services\PriceCalculator;
use BackedEnum;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use UnitEnum;

class PriceSimulator extends Page
{
    public ?array $data = [];

    public ?array $result = null;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calculator';

    protected string $view = 'filament-pricing::pages.price-simulator';

    protected static string | UnitEnum | null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Price Simulator';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Input Parameters')
                    ->schema([
                        Forms\Components\Select::make('product_type')
                            ->label('Product Type')
                            ->options([
                                'product' => 'Product',
                                'variant' => 'Variant',
                            ])
                            ->required()
                            ->live()
                            ->default('product'),

                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->required()
                            ->visible(fn (Get $get) => $get('product_type') === 'product')
                            ->options(function () {
                                return \AIArmada\Products\Models\Product::query()
                                    ->forOwner()
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => $p->name . ' (Base: RM' . number_format($p->price / 100, 2) . ')',
                                    ]);
                            }),

                        Forms\Components\Select::make('variant_id')
                            ->label('Variant')
                            ->searchable()
                            ->required()
                            ->visible(fn (Get $get) => $get('product_type') === 'variant')
                            ->options(function () {
                                $productIds = \AIArmada\Products\Models\Product::query()
                                    ->forOwner()
                                    ->pluck('id');

                                return \AIArmada\Products\Models\Variant::query()
                                    ->with('product')
                                    ->whereIn('product_id', $productIds)
                                    ->get()
                                    ->mapWithKeys(fn ($v) => [
                                        $v->id => $v->product->name . ' - ' . $v->sku . ' (RM' . number_format(($v->price ?? $v->product->price) / 100, 2) . ')',
                                    ]);
                            }),

                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->searchable()
                            ->helperText('Optional: simulate for a specific customer')
                            ->options(function () {
                                $owner = app()->bound(OwnerResolverInterface::class)
                                    ? app(OwnerResolverInterface::class)->resolve()
                                    : null;

                                return \AIArmada\Customers\Models\Customer::query()
                                    ->forOwner($owner)
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [
                                        $c->id => $c->full_name . ' (' . $c->email . ')',
                                    ]);
                            }),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1),

                        Forms\Components\DateTimePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->native(false)
                            ->helperText('Simulate pricing at a specific date/time'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function calculate(): void
    {
        $data = $this->form->getState();

        $owner = app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        // Get the priceable
        $priceable = null;
        if ($data['product_type'] === 'product') {
            $priceable = \AIArmada\Products\Models\Product::query()
                ->forOwner()
                ->find($data['product_id']);
        } else {
            $productIds = \AIArmada\Products\Models\Product::query()
                ->forOwner()
                ->pluck('id');

            $priceable = \AIArmada\Products\Models\Variant::query()
                ->whereIn('product_id', $productIds)
                ->find($data['variant_id']);
        }

        if (! $priceable) {
            $this->result = null;

            return;
        }

        // Get customer if provided
        $customer = $data['customer_id']
            ? \AIArmada\Customers\Models\Customer::query()->forOwner($owner)->find($data['customer_id'])
            : null;

        // Calculate price using PriceCalculator
        $pricingService = app(PriceCalculator::class);
        $context = $customer ? ['customer_id' => $customer->id] : [];
        /** @var \AIArmada\Pricing\Contracts\Priceable $priceable */
        $priceResult = $pricingService->calculate(
            item: $priceable,
            quantity: (int) $data['quantity'],
            context: $context
        );

        $this->result = [
            'original_price' => $priceResult->originalPrice,
            'final_price' => $priceResult->finalPrice,
            'discount_amount' => $priceResult->discountAmount,
            'discount_source' => $priceResult->discountSource,
            'discount_percentage' => $priceResult->discountPercentage,
            'price_list_name' => $priceResult->priceListName,
            'tier_description' => $priceResult->tierDescription,
            'promotion_name' => $priceResult->promotionName,
            'breakdown' => $priceResult->breakdown,
            'quantity' => (int) $data['quantity'],
            'unit_price' => $priceResult->finalPrice,
            'total_price' => $priceResult->finalPrice * (int) $data['quantity'],
        ];
    }

    public function clear(): void
    {
        $this->result = null;
        $this->form->fill();
    }

    public function resultInfolist(Schema $schema): Schema
    {
        if (! $this->result) {
            return $schema->schema([]);
        }

        return $schema
            ->state($this->result)
            ->schema([
                Section::make('Price Calculation Result')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('original_price')
                                    ->label('Original Price (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('final_price')
                                    ->label('Final Price (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                TextEntry::make('discount_amount')
                                    ->label('Discount (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->color('danger'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('Quantity')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('total_price')
                                    ->label('Total Price')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->color('success'),
                            ]),
                    ]),

                Section::make('Applied Pricing Rules')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('price_list_name')
                                    ->label('Price List')
                                    ->placeholder('Default pricing')
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('promotion_name')
                                    ->label('Promotion')
                                    ->placeholder('No promotion applied')
                                    ->badge()
                                    ->color('warning'),

                                TextEntry::make('tier_description')
                                    ->label('Price Tier')
                                    ->placeholder('No tier pricing')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('discount_percentage')
                                    ->label('Discount Percentage')
                                    ->placeholder('0%')
                                    ->suffix('%')
                                    ->numeric(decimalPlaces: 2),
                            ]),

                        TextEntry::make('discount_source')
                            ->label('Discount Source')
                            ->placeholder('No discount applied')
                            ->columnSpanFull(),
                    ])
                    ->visible(
                        fn () => $this->result['price_list_name'] ||
                        $this->result['promotion_name'] ||
                        $this->result['tier_description'] ||
                        $this->result['discount_source']
                    ),

                Section::make('Breakdown')
                    ->schema([
                        RepeatableEntry::make('breakdown')
                            ->label('')
                            ->schema([
                                TextEntry::make('step')
                                    ->label('Step'),
                                TextEntry::make('value')
                                    ->label('Value')
                                    ->money('MYR'),
                            ])
                            ->columns(2),
                    ])
                    ->visible(fn () => ! empty($this->result['breakdown']))
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('calculate')
                ->label('Calculate Price')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->action('calculate'),
            \Filament\Actions\Action::make('clear')
                ->label('Clear')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action('clear')
                ->visible(fn () => $this->result !== null),
        ];
    }
}
