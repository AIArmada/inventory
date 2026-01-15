<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\SendInstruction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class RecentPayoutsWidget extends BaseWidget
{
    protected static ?int $sort = 12;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Payouts';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                tap(SendInstruction::query(), function ($query): void {
                    if (method_exists($query->getModel(), 'scopeForOwner')) {
                        $query->forOwner();
                    }
                })
                    ->with('bankAccount')
                    ->latest('created_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable(),

                TextColumn::make('bankAccount.name')
                    ->label('Recipient')
                    ->placeholder('Unknown'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => 'RM ' . number_format((float) $state, 2))
                    ->alignEnd(),

                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record): string => $record->stateLabel)
                    ->color(fn ($record): string => $record->stateColor()),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (SendInstruction $record): string => $this->getResourceViewUrl($record))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No recent payouts')
            ->emptyStateDescription('Payouts created via CHIP Send will appear here.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    private function getResourceViewUrl(SendInstruction $record): string
    {
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'admin';

        return route("filament.{$panelId}.resources.send-instructions.view", ['record' => $record]);
    }
}
