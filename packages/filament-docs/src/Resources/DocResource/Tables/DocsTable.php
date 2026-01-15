<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Tables;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\FilamentDocs\Actions\RecordPaymentAction;
use AIArmada\FilamentDocs\Exports\DocExporter;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class DocsTable
{
    public static function configure(Table $table): Table
    {
        $docTypes = config('docs.types', []);

        if (! is_array($docTypes)) {
            $docTypes = [];
        }

        $docTypeOptions = collect($docTypes)
            ->keys()
            ->mapWithKeys(static fn (string $type): array => [$type => Str::headline($type)])
            ->all();

        return $table
            ->columns([
                TextColumn::make('doc_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy'),

                TextColumn::make('doc_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (DocStatus $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('customer_data.name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('total')
                    ->label('Total')
                    ->money(fn (Doc $record): string => $record->currency)
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('issue_date')
                    ->label('Issue Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn (Doc $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->toggleable(),

                TextColumn::make('template.name')
                    ->label('Template')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('doc_type')
                    ->label('Type')
                    ->options($docTypeOptions),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(DocStatus::cases())->mapWithKeys(fn (DocStatus $status) => [$status->value => $status->label()])),

                Filter::make('overdue')
                    ->label('Overdue')
                    ->query(
                        fn (Builder $query): Builder => $query
                            ->where('due_date', '<', CarbonImmutable::now())
                            ->whereNotIn('status', [DocStatus::PAID->value, DocStatus::CANCELLED->value])
                    ),

                Filter::make('paid')
                    ->label('Paid')
                    ->query(fn (Builder $query): Builder => $query->where('status', DocStatus::PAID->value)),

                Filter::make('has_pdf')
                    ->label('Has PDF')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('pdf_path')),

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(
                        fn (Builder $query): Builder => $query->whereMonth('issue_date', CarbonImmutable::now()->month)
                            ->whereYear('issue_date', CarbonImmutable::now()->year)
                    ),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(DocExporter::class)
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->label('Export'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                ActionGroup::make([
                    Action::make('generate_pdf')
                        ->label('Generate PDF')
                        ->icon(Heroicon::OutlinedDocumentArrowDown)
                        ->action(function (Doc $record): void {
                            app(DocService::class)->generatePdf($record, save: true);
                            Notification::make()->title('PDF generated')->success()->send();
                        }),

                    RecordPaymentAction::make(),

                    Action::make('mark_sent')
                        ->label('Mark as Sent')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->visible(fn (Doc $record): bool => in_array($record->status, [DocStatus::DRAFT, DocStatus::PENDING]))
                        ->action(function (Doc $record): void {
                            $record->markAsSent();
                            Notification::make()->title('Marked as sent')->success()->send();
                        }),

                    Action::make('mark_paid')
                        ->label('Mark as Paid')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->color('success')
                        ->visible(fn (Doc $record): bool => $record->canBePaid())
                        ->action(function (Doc $record): void {
                            $record->markAsPaid();
                            Notification::make()->title('Marked as paid')->success()->send();
                        }),

                    DeleteAction::make()
                        ->icon(Heroicon::OutlinedTrash),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->tooltip('More actions'),
            ])
            ->toolbarActions([
                BulkAction::make('generate_pdfs')
                    ->label('Generate PDFs')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->action(function (Collection $records): void {
                        $docService = app(DocService::class);
                        /** @var Collection<int|string, Doc> $records */
                        $records->each(fn (Doc $record) => $docService->generatePdf($record, save: true));
                        Notification::make()->title('PDFs generated for ' . count($records) . ' documents')->success()->send();
                    }),

                BulkAction::make('mark_as_sent')
                    ->label('Mark as Sent')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->action(function (Collection $records): void {
                        /** @var Collection<int|string, Doc> $records */
                        $records->each(fn (Doc $record) => $record->markAsSent());
                        Notification::make()->title('Documents marked as sent')->success()->send();
                    }),

                BulkAction::make('delete_selected')
                    ->label('Delete Selected')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        /** @var Collection<int|string, Doc> $records */
                        $records->each(fn (Doc $record) => $record->delete());
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }
}
