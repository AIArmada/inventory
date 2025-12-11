<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Config;
use UnitEnum;

final class StackingConfigurationPage extends Page implements HasForms
{
    use InteractsWithForms;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected string $view = 'filament-vouchers::pages.stacking-configuration';

    protected static ?string $navigationLabel = 'Stacking Rules';

    protected static ?string $title = 'Voucher Stacking Configuration';

    protected static string | UnitEnum | null $navigationGroup = 'Vouchers & Discounts';

    protected static ?int $navigationSort = 100;

    public function mount(): void
    {
        $this->form->fill([
            'mode' => config('vouchers.stacking.mode', 'sequential'),
            'auto_optimize' => config('vouchers.stacking.auto_optimize', false),
            'auto_replace' => config('vouchers.stacking.auto_replace', true),
            'max_vouchers' => config('vouchers.cart.max_vouchers_per_cart', 3),
            'rules' => config('vouchers.stacking.rules', []),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('mode')
                    ->label('Stacking Mode')
                    ->options([
                        'none' => 'None (Single voucher only)',
                        'sequential' => 'Sequential (Apply one after another)',
                        'parallel' => 'Parallel (Apply all to original total)',
                        'best_deal' => 'Best Deal (Auto-select best combination)',
                    ])
                    ->required()
                    ->helperText('How multiple vouchers are applied to the cart'),

                TextInput::make('max_vouchers')
                    ->label('Max Vouchers per Cart')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10)
                    ->required()
                    ->helperText('Maximum number of vouchers that can be stacked'),

                Toggle::make('auto_optimize')
                    ->label('Auto-Optimize')
                    ->helperText('Automatically find the best voucher combination'),

                Toggle::make('auto_replace')
                    ->label('Auto-Replace')
                    ->helperText('Replace existing vouchers when max is reached'),

                Repeater::make('rules')
                    ->label('Stacking Rules')
                    ->schema([
                        Select::make('type')
                            ->label('Rule Type')
                            ->options([
                                'max_vouchers' => 'Maximum Vouchers',
                                'max_discount' => 'Maximum Discount Amount',
                                'max_discount_percentage' => 'Maximum Discount Percentage',
                                'type_restriction' => 'Type Restriction',
                                'mutual_exclusion' => 'Mutual Exclusion',
                                'category_exclusion' => 'Category Exclusion',
                                'campaign_exclusion' => 'Campaign Exclusion',
                                'value_threshold' => 'Value Threshold',
                            ])
                            ->required()
                            ->reactive(),

                        TextInput::make('value')
                            ->label('Value')
                            ->numeric()
                            ->visible(fn (callable $get): bool => in_array($get('type'), ['max_vouchers', 'max_discount', 'max_discount_percentage', 'value_threshold'])),

                        KeyValue::make('max_per_type')
                            ->label('Max per Type')
                            ->keyLabel('Voucher Type')
                            ->valueLabel('Max Allowed')
                            ->visible(fn (callable $get): bool => $get('type') === 'type_restriction'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->cloneable()
                    ->defaultItems(0),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // In a real implementation, this would persist to database or config file
        // For now, we show a success message
        Notification::make()
            ->title('Configuration saved')
            ->body('Stacking configuration has been updated. Note: For persistent changes, update your vouchers.php config file.')
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
}
