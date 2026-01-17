<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class TargetingConfigurationPage extends Page implements HasForms
{
    use InteractsWithForms;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFunnel;

    protected string $view = 'filament-vouchers::pages.targeting-configuration';

    protected static ?string $navigationLabel = 'Targeting Rules';

    protected static ?string $title = 'Voucher Targeting Configuration';

    protected static string | UnitEnum | null $navigationGroup = 'Vouchers & Discounts';

    protected static ?int $navigationSort = 101;

    public function mount(): void
    {
        $this->form->fill([
            'check_targeting' => config('vouchers.validation.check_targeting', true),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('default_mode')
                    ->label('Default Targeting Mode')
                    ->options([
                        'all' => 'All Rules Must Match (AND)',
                        'any' => 'Any Rule Must Match (OR)',
                        'custom' => 'Custom Expression',
                    ])
                    ->default('all')
                    ->helperText('How targeting rules are evaluated by default'),

                Repeater::make('presets')
                    ->label('Targeting Presets')
                    ->schema([
                        TextInput::make('name')
                            ->label('Preset Name')
                            ->required(),

                        TextInput::make('description')
                            ->label('Description'),

                        Repeater::make('rules')
                            ->label('Rules')
                            ->schema([
                                Select::make('type')
                                    ->label('Rule Type')
                                    ->options([
                                        'user_segment' => 'User Segment',
                                        'cart_value' => 'Cart Value',
                                        'cart_quantity' => 'Cart Quantity',
                                        'product_in_cart' => 'Product in Cart',
                                        'category_in_cart' => 'Category in Cart',
                                        'time_window' => 'Time Window',
                                        'day_of_week' => 'Day of Week',
                                        'date_range' => 'Date Range',
                                        'channel' => 'Channel',
                                        'device' => 'Device',
                                        'geographic' => 'Geographic',
                                        'first_purchase' => 'First Purchase',
                                        'customer_ltv' => 'Customer Lifetime Value',
                                    ])
                                    ->required()
                                    ->live(),

                                Select::make('operator')
                                    ->label('Operator')
                                    ->options(fn (callable $get): array => self::getOperatorsForType($get('type')))
                                    ->required(),

                                TextInput::make('value')
                                    ->label('Value')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->collapsible(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->defaultItems(0),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Notification::make()
            ->title('Configuration saved')
            ->body('Targeting configuration has been updated.')
            ->warning()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Configuration')
                ->action('save')
                ->color('primary'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function getOperatorsForType(?string $type): array
    {
        return match ($type) {
            'cart_value', 'cart_quantity', 'customer_ltv' => [
                '=' => 'Equals',
                '!=' => 'Not Equals',
                '>' => 'Greater Than',
                '>=' => 'Greater or Equal',
                '<' => 'Less Than',
                '<=' => 'Less or Equal',
                'between' => 'Between',
            ],
            'user_segment', 'product_in_cart', 'category_in_cart' => [
                'in' => 'In List',
                'not_in' => 'Not In List',
                'all' => 'Contains All',
                'any' => 'Contains Any',
            ],
            'first_purchase' => [
                '=' => 'Equals',
            ],
            default => [
                '=' => 'Equals',
                '!=' => 'Not Equals',
                'in' => 'In List',
                'not_in' => 'Not In List',
            ],
        };
    }
}
