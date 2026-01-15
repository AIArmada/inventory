<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\FilamentDocs\Resources\DocResource;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class PendingApprovalsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected string $view = 'filament-docs::pages.pending-approvals';

    public static function getNavigationLabel(): string
    {
        return __('Pending Approvals');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-docs.navigation.group', __('Documents'));
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-docs.resources.navigation_sort.pending_approvals', 15);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getPendingApprovalsCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'warning';
    }

    public static function getPendingApprovalsCount(): int
    {
        $userId = Auth::id();

        if (! $userId) {
            return 0;
        }

        $query = DocApproval::query()
            ->where('assigned_to', $userId)
            ->where('status', 'pending')
            ->whereHas('doc', function (Builder $docQuery): void {
                self::applyDocOwnerScope($docQuery);
            });

        return $query->count();
    }

    public function getTitle(): string | Htmlable
    {
        return __('My Pending Approvals');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('doc.doc_number')
                    ->label(__('Document'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (DocApproval $record): string => DocResource::getUrl('view', ['record' => $record->doc_id])),

                TextColumn::make('doc.doc_type')
                    ->label(__('Type'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('doc.customer_data.name')
                    ->label(__('Recipient'))
                    ->searchable(),

                TextColumn::make('doc.total')
                    ->label(__('Total'))
                    ->money(fn (DocApproval $record): string => $record->doc->currency ?? 'MYR')
                    ->sortable(),

                TextColumn::make('requestedBy.name')
                    ->label(__('Requested By'))
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label(__('Requested At'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label(__('Expires'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn (DocApproval $record): string => $record->expires_at?->isPast() ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('doc_type')
                    ->label(__('Document Type'))
                    ->options(fn (): array => Doc::query()
                        ->tap(function (Builder $query): void {
                            self::applyDocOwnerScope($query);
                        })
                        ->distinct()
                        ->pluck('doc_type', 'doc_type')
                        ->toArray()),
            ])
            ->actions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('comments')
                            ->label(__('Comments'))
                            ->rows(3),
                    ])
                    ->action(function (DocApproval $record, array $data): void {
                        self::assertCanActOnApproval($record);

                        $record->update([
                            'status' => 'approved',
                            'approved_at' => CarbonImmutable::now(),
                            'comments' => $data['comments'] ?? null,
                        ]);

                        Notification::make()
                            ->title(__('Document Approved'))
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('comments')
                            ->label(__('Reason for Rejection'))
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (DocApproval $record, array $data): void {
                        self::assertCanActOnApproval($record);

                        $record->update([
                            'status' => 'rejected',
                            'rejected_at' => CarbonImmutable::now(),
                            'comments' => $data['comments'],
                        ]);

                        Notification::make()
                            ->title(__('Document Rejected'))
                            ->warning()
                            ->send();
                    }),

                Action::make('view_document')
                    ->label(__('View'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (DocApproval $record): string => DocResource::getUrl('view', ['record' => $record->doc_id])),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No Pending Approvals'))
            ->emptyStateDescription(__('You have no documents waiting for your approval.'))
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    /**
     * @return Builder<DocApproval>
     */
    protected function getTableQuery(): Builder
    {
        $userId = Auth::id();

        if (! $userId) {
            return DocApproval::query()->whereRaw('1 = 0');
        }

        $query = DocApproval::query()
            ->with(['doc', 'requestedBy'])
            ->where('assigned_to', $userId)
            ->where('status', 'pending');

        return $query->whereHas('doc', function (Builder $docQuery): void {
            self::applyDocOwnerScope($docQuery);
        });
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('Refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->resetTable()),
        ];
    }

    private static function applyDocOwnerScope(Builder $query): void
    {
        if (! config('docs.owner.enabled', false)) {
            return;
        }

        /** @var Model|null $owner */
        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
    }

    private static function assertCanActOnApproval(DocApproval $approval): void
    {
        $userId = Auth::id();

        abort_unless($userId !== null, 403);
        abort_unless((string) $approval->assigned_to === (string) $userId, 403);
        abort_unless($approval->status === 'pending', 403);

        $exists = Doc::query()
            ->whereKey($approval->doc_id)
            ->tap(function (Builder $query): void {
                self::applyDocOwnerScope($query);
            })
            ->exists();

        abort_unless($exists, 404);
    }
}
