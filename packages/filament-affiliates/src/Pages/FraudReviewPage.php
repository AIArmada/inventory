<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
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
use UnitEnum;

final class FraudReviewPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string | UnitEnum | null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Fraud Review';

    protected static ?int $navigationSort = 15;

    protected static string $view = 'filament-affiliates::pages.fraud-review';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliateFraudSignal::query()
                    ->where('status', FraudSignalStatus::Pending)
                    ->with(['affiliate', 'conversion'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('signal_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'velocity_abuse' => 'danger',
                        'ip_duplicate' => 'warning',
                        'self_referral' => 'danger',
                        'suspicious_pattern' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('score')
                    ->label('Risk Score')
                    ->formatStateUsing(fn ($state): string => $state . '%'),

                Tables\Columns\TextColumn::make('detected_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('signal_type')
                    ->options([
                        'velocity_abuse' => 'Velocity Abuse',
                        'ip_duplicate' => 'IP Duplicate',
                        'self_referral' => 'Self Referral',
                        'suspicious_pattern' => 'Suspicious Pattern',
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
                        $record->update([
                            'status' => FraudSignalStatus::Dismissed,
                            'reviewed_at' => now(),
                            'reviewed_by' => auth()->id(),
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
                        $record->update([
                            'status' => FraudSignalStatus::Confirmed,
                            'reviewed_at' => now(),
                            'reviewed_by' => auth()->id(),
                            'review_notes' => $data['notes'],
                        ]);

                        if ($record->conversion) {
                            $record->conversion->update(['status' => 'rejected']);
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
                        $records->each(function ($record): void {
                            $record->update([
                                'status' => FraudSignalStatus::Dismissed,
                                'reviewed_at' => now(),
                                'reviewed_by' => auth()->id(),
                            ]);
                        });
                    }),

                BulkAction::make('bulk_reject')
                    ->label('Confirm Fraud (Selected)')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $records->each(function ($record): void {
                            $record->update([
                                'status' => FraudSignalStatus::Confirmed,
                                'reviewed_at' => now(),
                                'reviewed_by' => auth()->id(),
                            ]);

                            if ($record->conversion) {
                                $record->conversion->update(['status' => 'rejected']);
                            }
                        });
                    }),
            ]);
    }

    public function getViewData(): array
    {
        return [
            'pendingCount' => AffiliateFraudSignal::where('status', FraudSignalStatus::Pending)->count(),
            'criticalCount' => AffiliateFraudSignal::where('status', FraudSignalStatus::Pending)
                ->where('severity', 'critical')
                ->count(),
        ];
    }
}
