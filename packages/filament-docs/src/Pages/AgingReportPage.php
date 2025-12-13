<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Pages;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AgingReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string | UnitEnum | null $navigationGroup = 'Documents';

    protected static ?string $navigationLabel = 'Aging Report';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament-docs::pages.aging-report';

    public function getTitle(): string
    {
        return 'Accounts Receivable Aging Report';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Doc::query()
                    ->whereIn('status', [
                        DocStatus::PENDING,
                        DocStatus::SENT,
                        DocStatus::PARTIALLY_PAID,
                        DocStatus::OVERDUE,
                    ])
                    ->whereNotNull('due_date')
            )
            ->columns([
                TextColumn::make('doc_number')
                    ->label('Document #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('doc_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('customer_data.name')
                    ->label('Customer')
                    ->searchable()
                    ->default('-'),

                TextColumn::make('issue_date')
                    ->label('Issue Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('days_overdue')
                    ->label('Days Overdue')
                    ->getStateUsing(function (Doc $record): int {
                        if (! $record->due_date) {
                            return 0;
                        }

                        return max(0, (int) now()->diffInDays($record->due_date, false));
                    })
                    ->badge()
                    ->color(function (int $state): string {
                        if ($state === 0) {
                            return 'success';
                        }
                        if ($state <= 30) {
                            return 'warning';
                        }
                        if ($state <= 60) {
                            return 'orange';
                        }

                        return 'danger';
                    }),

                TextColumn::make('aging_bucket')
                    ->label('Aging')
                    ->getStateUsing(function (Doc $record): string {
                        if (! $record->due_date) {
                            return 'Current';
                        }

                        $days = max(0, (int) now()->diffInDays($record->due_date, false));

                        if ($days === 0) {
                            return 'Current';
                        }
                        if ($days <= 30) {
                            return '1-30 Days';
                        }
                        if ($days <= 60) {
                            return '31-60 Days';
                        }
                        if ($days <= 90) {
                            return '61-90 Days';
                        }

                        return '90+ Days';
                    })
                    ->badge(),

                TextColumn::make('total')
                    ->label('Amount')
                    ->money(fn (Doc $record): string => $record->currency)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state->color()),
            ])
            ->filters([
                SelectFilter::make('aging_bucket')
                    ->label('Aging')
                    ->options([
                        'current' => 'Current',
                        '1-30' => '1-30 Days',
                        '31-60' => '31-60 Days',
                        '61-90' => '61-90 Days',
                        '90+' => '90+ Days',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['value']) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'current' => $query->where('due_date', '>=', now()),
                            '1-30' => $query->whereBetween('due_date', [now()->subDays(30), now()->subDay()]),
                            '31-60' => $query->whereBetween('due_date', [now()->subDays(60), now()->subDays(31)]),
                            '61-90' => $query->whereBetween('due_date', [now()->subDays(90), now()->subDays(61)]),
                            '90+' => $query->where('due_date', '<', now()->subDays(90)),
                            default => $query,
                        };
                    }),

                SelectFilter::make('status')
                    ->options(collect(DocStatus::cases())
                        ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                        ->all()),
            ])
            ->defaultSort('due_date', 'asc')
            ->recordActions([
                Action::make('view')
                    ->url(fn (Doc $record): string => route('filament.admin.resources.docs.view', $record))
                    ->icon('heroicon-o-eye'),
            ]);
    }

    /**
     * Get aging summary data for the header cards.
     *
     * @return array<string, array{count: int, amount: float}>
     */
    public function getAgingSummary(): array
    {
        $docs = Doc::query()
            ->whereIn('status', [
                DocStatus::PENDING,
                DocStatus::SENT,
                DocStatus::PARTIALLY_PAID,
                DocStatus::OVERDUE,
            ])
            ->whereNotNull('due_date')
            ->get();

        $summary = [
            'current' => ['count' => 0, 'amount' => 0],
            '1-30' => ['count' => 0, 'amount' => 0],
            '31-60' => ['count' => 0, 'amount' => 0],
            '61-90' => ['count' => 0, 'amount' => 0],
            '90+' => ['count' => 0, 'amount' => 0],
        ];

        foreach ($docs as $doc) {
            $days = max(0, (int) now()->diffInDays($doc->due_date, false));
            $bucket = match (true) {
                $days === 0 => 'current',
                $days <= 30 => '1-30',
                $days <= 60 => '31-60',
                $days <= 90 => '61-90',
                default => '90+',
            };

            $summary[$bucket]['count']++;
            $summary[$bucket]['amount'] += (float) $doc->total;
        }

        return $summary;
    }
}
