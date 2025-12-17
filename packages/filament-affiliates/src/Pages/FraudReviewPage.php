<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class FraudReviewPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string | UnitEnum | null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Fraud Review';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament-affiliates::pages.fraud-review';

    public function table(Table $table): Table
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false) && app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        return $table
            ->query(
                AffiliateFraudSignal::query()
                    ->when(
                        (bool) config('affiliates.owner.enabled', false),
                        fn ($query) => $query->whereHas('affiliate', function ($affiliateQuery) use ($owner): void {
                            if (! $owner) {
                                $affiliateQuery->whereNull('owner_type')->whereNull('owner_id');

                                return;
                            }

                            $affiliateQuery->where(function ($builder) use ($owner): void {
                                $builder->where('owner_type', $owner->getMorphClass())
                                    ->where('owner_id', $owner->getKey())
                                    ->orWhere(function ($inner): void {
                                        $inner->whereNull('owner_type')->whereNull('owner_id');
                                    });
                            });
                        }),
                    )
                    ->where('status', FraudSignalStatus::Detected)
                    ->with(['affiliate', 'conversion'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rule_code')
                    ->label('Signal')
                    ->badge()
                    ->color(fn ($state): string => match ((string) $state) {
                        'velocity' => 'danger',
                        'velocity_abuse' => 'danger',
                        'ip_duplicate' => 'warning',
                        'self_referral' => 'danger',
                        'pattern' => 'warning',
                        'suspicious_pattern' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof FraudSeverity ? $state->value : (string) $state) {
                        FraudSeverity::Critical->value => 'danger',
                        FraudSeverity::High->value => 'warning',
                        FraudSeverity::Medium->value => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('risk_points')
                    ->label('Risk Score')
                    ->formatStateUsing(fn ($state): string => $state . '%'),

                Tables\Columns\TextColumn::make('detected_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rule_code')
                    ->options([
                        'velocity' => 'Velocity Abuse',
                        'ip_duplicate' => 'IP Duplicate',
                        'self_referral' => 'Self Referral',
                        'pattern' => 'Suspicious Pattern',
                        'cookie_stuffing' => 'Cookie Stuffing',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'critical' => 'Critical',
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (AffiliateFraudSignal $record): void {
                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $record->update([
                            'status' => FraudSignalStatus::Dismissed,
                            'reviewed_at' => now(),
                            'reviewed_by' => $reviewedBy === null ? null : (string) $reviewedBy,
                        ]);
                    }),

                Action::make('reject')
                    ->label('Confirm Fraud')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Review Notes')
                            ->required(),
                    ])
                    ->action(function (AffiliateFraudSignal $record, array $data): void {
                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $record->update([
                            'status' => FraudSignalStatus::Confirmed,
                            'reviewed_at' => now(),
                            'reviewed_by' => $reviewedBy === null ? null : (string) $reviewedBy,
                            'evidence' => array_merge($record->evidence ?? [], [
                                'review_notes' => $data['notes'],
                            ]),
                        ]);

                        if ($record->conversion) {
                            $record->conversion->update(['status' => ConversionStatus::Rejected]);
                        }
                    }),

                ViewAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('bulk_approve')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $records->each(function ($record) use ($reviewedBy): void {
                            $record->update([
                                'status' => FraudSignalStatus::Dismissed,
                                'reviewed_at' => now(),
                                'reviewed_by' => $reviewedBy === null ? null : (string) $reviewedBy,
                            ]);
                        });
                    }),

                BulkAction::make('bulk_reject')
                    ->label('Confirm Fraud (Selected)')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $records->each(function ($record) use ($reviewedBy): void {
                            $record->update([
                                'status' => FraudSignalStatus::Confirmed,
                                'reviewed_at' => now(),
                                'reviewed_by' => $reviewedBy === null ? null : (string) $reviewedBy,
                            ]);

                            if ($record->conversion) {
                                $record->conversion->update(['status' => ConversionStatus::Rejected]);
                            }
                        });
                    }),
            ]);
    }

    public function getViewData(): array
    {
        return [
            'pendingCount' => AffiliateFraudSignal::where('status', FraudSignalStatus::Detected)->count(),
            'criticalCount' => AffiliateFraudSignal::where('status', FraudSignalStatus::Detected)
                ->where('severity', 'critical')
                ->count(),
        ];
    }
}
