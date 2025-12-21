<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
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
        return $table
            ->query(
                OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
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
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.fraud.update') ?? false)
                    ->action(function (AffiliateFraudSignal $record): void {
                        Gate::authorize('update', $record);

                        $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $signal->update([
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
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.fraud.update') ?? false)
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Review Notes')
                            ->required(),
                    ])
                    ->action(function (AffiliateFraudSignal $record, array $data): void {
                        Gate::authorize('update', $record);

                        $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $signal->update([
                            'status' => FraudSignalStatus::Confirmed,
                            'reviewed_at' => now(),
                            'reviewed_by' => $reviewedBy === null ? null : (string) $reviewedBy,
                            'evidence' => array_merge($signal->evidence ?? [], [
                                'review_notes' => $data['notes'],
                            ]),
                        ]);

                        if ($signal->conversion) {
                            $signal->conversion->update(['status' => ConversionStatus::Rejected]);
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
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.fraud.update') ?? false)
                    ->action(function ($records): void {
                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $records->each(function ($record) use ($reviewedBy): void {
                            Gate::authorize('update', $record);

                            $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                                ->whereKey($record->getKey())
                                ->firstOrFail();

                            $signal->update([
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
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.fraud.update') ?? false)
                    ->action(function ($records): void {
                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $records->each(function ($record) use ($reviewedBy): void {
                            Gate::authorize('update', $record);

                            $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                                ->whereKey($record->getKey())
                                ->firstOrFail();

                            $signal->update([
                                'status' => FraudSignalStatus::Confirmed,
                                'reviewed_at' => now(),
                                'reviewed_by' => $reviewedBy === null ? null : (string) $reviewedBy,
                            ]);

                            if ($signal->conversion) {
                                $signal->conversion->update(['status' => ConversionStatus::Rejected]);
                            }
                        });
                    }),
            ]);
    }

    public function getViewData(): array
    {
        $base = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
            ->where('status', FraudSignalStatus::Detected);

        return [
            'pendingCount' => (clone $base)->count(),
            'criticalCount' => (clone $base)
                ->where('severity', 'critical')
                ->count(),
        ];
    }
}
