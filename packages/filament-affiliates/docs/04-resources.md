---
title: Resources
---

# Resources

The plugin provides five Filament resources for managing affiliates.

## AffiliateResource

Manage affiliate accounts, status, and assignments.

### Features

- View/edit affiliate profiles
- Assign to programs and tiers
- View referral network
- Manage sub-affiliates
- Track attribution links

### Customization

Extend the resource to customize:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentAffiliates\Resources\AffiliateResource as BaseResource;

class AffiliateResource extends BaseResource
{
    protected static ?string $navigationLabel = 'Partners';

    protected static ?string $modelLabel = 'Partner';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::pending()->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                ...parent::getFormSchema(),
                Forms\Components\Section::make('Custom Fields')
                    ->schema([
                        Forms\Components\TextInput::make('company_name'),
                    ]),
            ]);
    }
}
```

### Available Actions

| Action | Description |
|--------|-------------|
| Approve | Approve pending affiliate |
| Suspend | Suspend active affiliate |
| Reactivate | Reactivate suspended affiliate |
| AssignProgram | Assign to program with tier |
| GenerateLink | Create attribution link |

## AffiliateConversionResource

Track all conversions and commissions.

### Table Columns

- Affiliate name
- Conversion type (Sale, Lead, Click, Registration)
- Reference (`external_reference`)
- Value (`value_minor`)
- Commission (calculated)
- Status
- Created date

### Filters

```php
Tables\Filters\SelectFilter::make('status')
    ->options(ConversionStatus::class),

Tables\Filters\SelectFilter::make('type')
    ->options(ConversionType::class),

Tables\Filters\Filter::make('created_at')
    ->form([
        Forms\Components\DatePicker::make('from'),
        Forms\Components\DatePicker::make('until'),
    ]),
```

### Actions

| Action | Description |
|--------|-------------|
| View | View conversion details |
| Approve | Approve pending conversion |
| Reject | Reject fraudulent conversion |
| Flag | Flag for fraud review |

## AffiliatePayoutResource

Manage affiliate payouts and payments.

### Status Flow

```
Pending → Processing → Completed
              ↓
           Failed
              ↓
          Cancelled
```

### Bulk Actions

```php
Tables\Actions\BulkActionGroup::make([
    Tables\Actions\BulkAction::make('process')
        ->label('Process Selected')
        ->action(fn (Collection $records) => $this->processPayouts($records)),

    Tables\Actions\BulkAction::make('export')
        ->label('Export for Payment')
        ->action(fn (Collection $records) => $this->exportPayouts($records)),
]);
```

### Customization

Add custom payment methods:

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Select::make('payment_method')
            ->options([
                'paypal' => 'PayPal',
                'stripe' => 'Stripe Connect',
                'bank_transfer' => 'Bank Transfer',
                'wise' => 'Wise',
                'crypto' => 'Cryptocurrency',
            ]),
    ]);
}
```

## AffiliateProgramResource

Configure affiliate programs and commission structures.

### Form Schema

```php
Forms\Components\Section::make('Program Details')
    ->schema([
        Forms\Components\TextInput::make('name')
            ->required(),

        Forms\Components\Textarea::make('description'),

        Forms\Components\Toggle::make('is_active')
            ->default(true),

        Forms\Components\Toggle::make('is_public')
            ->helperText('Allow self-enrollment'),
    ]),

Forms\Components\Section::make('Commission Structure')
    ->schema([
        Forms\Components\Repeater::make('tiers')
            ->relationship()
            ->schema([
                Forms\Components\TextInput::make('name'),
                Forms\Components\TextInput::make('commission_rate')
                    ->numeric()
                    ->suffix('%'),
            ]),
    ]),
```

### Relation Managers

| Manager | Description |
|---------|-------------|
| TiersRelationManager | Manage program tiers |
| AffiliatesRelationManager | View enrolled affiliates |
| RulesRelationManager | Configure commission rules |

## AffiliateFraudSignalResource

Review and manage fraud alerts.

### Severity Levels

| Level | Color | Description |
|-------|-------|-------------|
| Low | Gray | Minor anomaly |
| Medium | Yellow | Suspicious pattern |
| High | Orange | Likely fraud |
| Critical | Red | Confirmed fraud |

### Actions

```php
Tables\Actions\Action::make('dismiss')
    ->label('Dismiss')
    ->action(fn (FraudSignal $record) => $record->dismiss()),

Tables\Actions\Action::make('confirm')
    ->label('Confirm Fraud')
    ->color('danger')
    ->requiresConfirmation()
    ->action(fn (FraudSignal $record) => $record->confirmFraud()),

Tables\Actions\Action::make('block_affiliate')
    ->label('Block Affiliate')
    ->color('danger')
    ->requiresConfirmation()
    ->action(fn (FraudSignal $record) => $record->affiliate->suspend()),
```

## Overriding Resources

Register custom resources in your panel:

```php
use App\Filament\Resources\AffiliateResource;
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

FilamentAffiliatesPlugin::make()
    ->resources([
        AffiliateResource::class,
        // Use default for others...
    ]);
```

## Feature-Gated Registration

The plugin resolves resources from `filament-affiliates.features.admin`:

- `conversions` controls `AffiliateConversionResource`
- `payouts` controls `AffiliatePayoutResource`
- `programs` controls `AffiliateProgramResource`
- `fraud_monitoring` controls `AffiliateFraudSignalResource`

When `affiliates.features.commission_tracking.enabled` is false, payout/program resources are automatically disabled.

## Adding Custom Columns

Extend table columns in your resource:

```php
public static function table(Table $table): Table
{
    return parent::table($table)
        ->columns([
            ...parent::getTableColumns(),
            Tables\Columns\TextColumn::make('custom_field'),
        ]);
}
```
