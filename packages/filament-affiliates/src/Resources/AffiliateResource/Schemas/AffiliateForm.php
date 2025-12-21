<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Schemas;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AffiliateForm
{
    public static function configure(Schema $schema): Schema
    {
        $currency = (string) config('affiliates.currency.default', 'USD');

        return $schema->components([
            Section::make('Affiliate Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('code')
                            ->label('Tracking Code')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if ($state !== null) {
                                    $set('code', mb_strtoupper($state));
                                }
                            }),

                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(120),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options(self::enumOptions(AffiliateStatus::class))
                            ->default(AffiliateStatus::Draft->value),
                    ]),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3),

                    Grid::make(3)->schema([
                        TextInput::make('default_voucher_code')
                            ->label('Default Voucher Code')
                            ->maxLength(64),

                        TextInput::make('tracking_domain')
                            ->label('Tracking Domain')
                            ->placeholder('track.example.com'),

                        TextInput::make('payout_terms')
                            ->label('Payout Terms')
                            ->placeholder('Net-30'),
                        Select::make('parent_affiliate_id')
                            ->label('Parent Affiliate (Upline)')
                            ->relationship('parent', 'name', modifyQueryUsing: function (Builder $affiliateQuery): Builder {
                                if (! (bool) config('affiliates.owner.enabled', false)) {
                                    return $affiliateQuery;
                                }

                                /** @var Model|null $owner */
                                $owner = OwnerContext::resolve();
                                $includeGlobal = (bool) config('affiliates.owner.include_global', false);

                                $scoped = $affiliateQuery->withoutGlobalScope(OwnerScope::class);

                                return OwnerQuery::applyToEloquentBuilder($scoped, $owner, $includeGlobal);
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Optional upline for multi-level commission sharing')
                            ->disableOptionWhen(fn ($value, callable $get): bool => $value === $get('id')),
                    ]),
                ]),

            Section::make('Commission Policy')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('commission_type')
                            ->label('Type')
                            ->required()
                            ->options(self::enumOptions(CommissionType::class))
                            ->default(CommissionType::Percentage->value),

                        TextInput::make('commission_rate')
                            ->label('Rate')
                            ->numeric()
                            ->required()
                            ->suffix(fn (Get $get): string => $get('commission_type') === CommissionType::Percentage->value ? '%' : $get('currency') ?? $currency)
                            ->formatStateUsing(fn (?int $state, Get $get): ?string => $state === null
                                ? null
                                : (
                                    $get('commission_type') === CommissionType::Percentage->value
                                    ? number_format($state / 100, 2, '.', '')
                                    : number_format($state / 100, 2, '.', '')
                                ))
                            ->dehydrateStateUsing(
                                fn (?string $state, Get $get): ?int => $state === null || $state === ''
                                ? null
                                : (int) round((float) $state * 100)
                            ),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'USD' => 'USD',
                                'MYR' => 'MYR',
                                'SGD' => 'SGD',
                                'IDR' => 'IDR',
                            ])
                            ->default($currency),
                    ]),
                ])
                ->collapsible(),

            Section::make('Communication')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->maxLength(120),

                        TextInput::make('website_url')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),
                    ]),
                ])
                ->collapsed(),

            Section::make('Metadata')
                ->schema([
                    KeyValue::make('metadata')
                        ->label('Metadata')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->addActionLabel('Add entry'),
                ])
                ->collapsed(),
        ]);
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, string>
     */
    private static function enumOptions(string $enum): array
    {
        /** @var array<int, BackedEnum> $cases */
        $cases = $enum::cases();

        return collect($cases)
            ->mapWithKeys(static fn ($case): array => [$case->value => method_exists($case, 'label') ? $case->label() : ucfirst($case->value)])
            ->toArray();
    }
}
