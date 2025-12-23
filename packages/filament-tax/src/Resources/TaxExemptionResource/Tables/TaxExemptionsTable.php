<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Tables;

use AIArmada\FilamentTax\Actions\DownloadTaxExemptionCertificateAction;
use AIArmada\FilamentTax\Support\FilamentTaxAuthz;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Support\TaxOwnerScope;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TaxExemptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exemptable.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A')
                    ->description(fn (TaxExemption $record): ?string => $record->exemptable_type === 'AIArmada\\Customers\\Models\\CustomerGroup' ? 'Group' : null),

                TextColumn::make('certificate_number')
                    ->label('Certificate #')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('taxZone.name')
                    ->label('Zone')
                    ->badge()
                    ->placeholder('All Zones')
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('starts_at')
                    ->label('Valid From')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('Never')
                    ->color(function (TaxExemption $record): string {
                        if (! $record->expires_at) {
                            return 'success';
                        }

                        if ($record->expires_at->isPast()) {
                            return 'danger';
                        }

                        if ($record->expires_at->isBefore(now()->addDays(30))) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->icon(function (TaxExemption $record): string {
                        if (! $record->expires_at) {
                            return 'heroicon-o-infinity';
                        }

                        if ($record->expires_at->isPast()) {
                            return 'heroicon-o-x-circle';
                        }

                        if ($record->expires_at->isBefore(now()->addDays(30))) {
                            return 'heroicon-o-exclamation-triangle';
                        }

                        return 'heroicon-o-check-circle';
                    }),
            ])
            ->defaultSort('expires_at', 'asc')
            ->filters([
                SelectFilter::make('tax_zone_id')
                    ->label('Zone')
                    ->relationship(
                        'taxZone',
                        'name',
                        fn (Builder $query): Builder => TaxOwnerScope::applyToOwnedQuery($query),
                    ),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                Filter::make('expiring_soon')
                    ->label('Expiring in 30 days')
                    ->query(
                        fn ($query) => $query
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>=', now())
                            ->where('expires_at', '<=', now()->addDays(30))
                    )
                    ->toggle(),

                Filter::make('expired')
                    ->label('Expired')
                    ->query(
                        fn ($query) => $query
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<', now())
                    )
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    FilamentTaxAuthz::requirePermission(
                        Action::make('download_certificate')
                            ->label('Download Certificate')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->visible(fn (TaxExemption $record): bool => filled($record->document_path))
                            ->action(function (TaxExemption $record): mixed {
                                try {
                                    return app(DownloadTaxExemptionCertificateAction::class)->execute($record);
                                } catch (NotFoundHttpException) {
                                    Notification::make()
                                        ->title('Certificate document not found')
                                        ->danger()
                                        ->send();

                                    return null;
                                }
                            }),
                        'tax.exemptions.download',
                    ),
                    FilamentTaxAuthz::requirePermission(
                        Action::make('approve')
                            ->label('Approve')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->color('success')
                            ->visible(fn (TaxExemption $record): bool => $record->status === 'pending')
                            ->requiresConfirmation()
                            ->action(fn (TaxExemption $record) => $record->approve())
                            ->successNotificationTitle('Exemption approved'),
                        'tax.exemptions.approve',
                    ),
                    FilamentTaxAuthz::requirePermission(
                        Action::make('renew')
                            ->label('Renew')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->color('warning')
                            ->form([
                                DatePicker::make('new_expires_at')
                                    ->label('New Expiry Date')
                                    ->required()
                                    ->native(false)
                                    ->after('today'),
                            ])
                            ->action(function (TaxExemption $record, array $data): void {
                                $record->update(['expires_at' => $data['new_expires_at']]);
                            })
                            ->successNotificationTitle('Exemption renewed'),
                        'tax.exemptions.renew',
                    ),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                FilamentTaxAuthz::requirePermission(
                    BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->approve())
                        ->deselectRecordsAfterCompletion(),
                    'tax.exemptions.approve',
                ),
                FilamentTaxAuthz::requirePermission(
                    BulkAction::make('reject')
                        ->label('Reject Selected')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->reject('Bulk rejected'))
                        ->deselectRecordsAfterCompletion(),
                    'tax.exemptions.reject',
                ),
                FilamentTaxAuthz::requirePermission(
                    BulkAction::make('delete')
                        ->label('Delete Selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->delete())
                        ->deselectRecordsAfterCompletion(),
                    'tax.exemptions.delete',
                ),
            ]);
    }
}
