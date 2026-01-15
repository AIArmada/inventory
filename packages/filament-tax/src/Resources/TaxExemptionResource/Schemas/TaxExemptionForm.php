<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Schemas;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Tax\Enums\ExemptionStatus;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Support\TaxOwnerScope;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as GetFormState;
use Filament\Schemas\Components\Utilities\Set as SetFormState;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class TaxExemptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Customer Information')
                            ->schema([
                                Select::make('exemptable_type')
                                    ->label('Entity Type')
                                    ->options(self::getExemptableTypes())
                                    ->required()
                                    ->live()
                                    ->default(fn (): ?string => array_key_first(self::getExemptableTypes()))
                                    ->afterStateUpdated(function (SetFormState $set): void {
                                        $set('exemptable_id', null);
                                    }),

                                Select::make('exemptable_id')
                                    ->label('Customer/Entity')
                                    ->searchable()
                                    ->required()
                                    ->getSearchResultsUsing(function (string $search, GetFormState $get): array {
                                        $type = $get('exemptable_type');

                                        if (! $type || ! class_exists($type) || ! is_a($type, Model::class, true)) {
                                            return [];
                                        }

                                        /** @var Builder<Model> $query */
                                        $query = $type::query();

                                        if (in_array(HasOwner::class, class_uses_recursive($type), true)) {
                                            $query = TaxOwnerScope::applyToOwnedQuery($query);
                                        }

                                        if ($type === Customer::class) {
                                            /** @var Builder<Customer> $customerQuery */
                                            $customerQuery = Customer::query();

                                            if (in_array(HasOwner::class, class_uses_recursive($type), true)) {
                                                $customerQuery = TaxOwnerScope::applyToOwnedQuery($customerQuery);
                                            }

                                            return $customerQuery
                                                ->where(function (Builder $builder) use ($search): void {
                                                    $builder
                                                        ->where('full_name', 'like', "%{$search}%")
                                                        ->orWhere('email', 'like', "%{$search}%");
                                                })
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(fn (Customer $customer): array => [
                                                    (string) $customer->getKey() => (string) $customer->getAttribute('full_name') . ' (' . (string) $customer->getAttribute('email') . ')',
                                                ])
                                                ->toArray();
                                        }

                                        if ($type === CustomerGroup::class) {
                                            /** @var Builder<CustomerGroup> $groupQuery */
                                            $groupQuery = CustomerGroup::query();

                                            if (in_array(HasOwner::class, class_uses_recursive($type), true)) {
                                                $groupQuery = TaxOwnerScope::applyToOwnedQuery($groupQuery);
                                            }

                                            return $groupQuery
                                                ->where('name', 'like', "%{$search}%")
                                                ->limit(50)
                                                ->pluck('name', 'id')
                                                ->toArray();
                                        }

                                        return $query
                                            ->where(function (Builder $builder) use ($search): void {
                                                $builder
                                                    ->where('name', 'like', "%{$search}%")
                                                    ->orWhere('email', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (Model $model): array => [
                                                (string) $model->getKey() => (string) ($model->getAttribute('name') ?? $model->getAttribute('email') ?? $model->getKey()),
                                            ])
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value, GetFormState $get): ?string {
                                        $type = $get('exemptable_type');

                                        if ($value === null || ! $type || ! class_exists($type) || ! is_a($type, Model::class, true)) {
                                            return null;
                                        }

                                        /** @var Builder<Model> $query */
                                        $query = $type::query();

                                        if (in_array(HasOwner::class, class_uses_recursive($type), true)) {
                                            $query = TaxOwnerScope::applyToOwnedQuery($query);
                                        }

                                        $record = $query->whereKey($value)->first();

                                        if (! $record) {
                                            return null;
                                        }

                                        if ($type === Customer::class) {
                                            return (string) $record->getAttribute('full_name') . ' (' . (string) $record->getAttribute('email') . ')';
                                        }

                                        return (string) ($record->getAttribute('name') ?? $record->getAttribute('email') ?? $record->getKey());
                                    }),

                                Select::make('tax_zone_id')
                                    ->label('Tax Zone')
                                    ->relationship(
                                        'taxZone',
                                        'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => TaxOwnerScope::applyToOwnedQuery($query),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('All Zones')
                                    ->helperText('Leave empty to apply exemption to all zones'),
                            ])
                            ->columns(3),

                        Section::make('Certificate Details')
                            ->schema([
                                TextInput::make('certificate_number')
                                    ->label('Certificate Number')
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                FileUpload::make('document_path')
                                    ->label('Certificate Document')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(5120)
                                    ->disk('local')
                                    ->directory('tax-exemptions')
                                    ->visibility('private'),

                                Textarea::make('reason')
                                    ->label('Exemption Reason')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Validity Period')
                            ->schema([
                                DatePicker::make('starts_at')
                                    ->label('Effective From')
                                    ->default(CarbonImmutable::now())
                                    ->native(false),

                                DatePicker::make('expires_at')
                                    ->label('Expires At')
                                    ->native(false)
                                    ->after('starts_at')
                                    ->helperText('Leave blank for permanent exemption'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options(ExemptionStatus::class)
                                    ->default(ExemptionStatus::Pending)
                                    ->required(),

                                Placeholder::make('status_info')
                                    ->label('Status Info')
                                    ->content(function (?TaxExemption $record): string {
                                        if (! $record) {
                                            return 'New exemption - pending review';
                                        }

                                        if ($record->expires_at?->isPast()) {
                                            return '⚠️ Expired on ' . $record->expires_at->format('d M Y');
                                        }

                                        if ($record->expires_at?->isBefore(CarbonImmutable::now()->addDays(30))) {
                                            return '⏰ Expiring in ' . $record->expires_at->diffForHumans();
                                        }

                                        if ($record->status === ExemptionStatus::Approved) {
                                            return '✅ Active & Approved';
                                        }

                                        if ($record->status === ExemptionStatus::Rejected) {
                                            return '❌ Rejected';
                                        }

                                        return '⏳ Pending Review';
                                    }),

                                Placeholder::make('verified_info')
                                    ->label('Verified')
                                    ->content(function (?TaxExemption $record): string {
                                        if (! $record || ! $record->verified_at) {
                                            return 'Not yet verified';
                                        }

                                        return $record->verified_at->format('d M Y H:i');
                                    }),
                            ]),

                        Section::make('Internal Notes')
                            ->schema([
                                Textarea::make('rejection_reason')
                                    ->label('Rejection Reason / Notes')
                                    ->rows(4)
                                    ->helperText('Required if rejecting the exemption'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    /**
     * @return array<string, string>
     */
    private static function getExemptableTypes(): array
    {
        $types = [];

        if (class_exists('AIArmada\\Customers\\Models\\Customer')) {
            $types['AIArmada\\Customers\\Models\\Customer'] = 'Customer';
        }

        if (class_exists('AIArmada\\Customers\\Models\\CustomerGroup')) {
            $types['AIArmada\\Customers\\Models\\CustomerGroup'] = 'Customer Group';
        }

        // Fallback if customers package not installed
        if (empty($types)) {
            $types['App\\Models\\User'] = 'User';
        }

        return $types;
    }
}
