<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class FraudAlertWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected static ?string $heading = 'Fraud Alerts';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliateFraudSignal::query()
                    ->with('affiliate')
                    ->where('status', 'detected')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('detected_at')
                    ->label('Detected')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable(),

                Tables\Columns\TextColumn::make('signal_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (FraudSeverity $state) => match ($state) {
                        FraudSeverity::Low => 'gray',
                        FraudSeverity::Medium => 'warning',
                        FraudSeverity::High => 'danger',
                        FraudSeverity::Critical => 'danger',
                    }),

                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->formatStateUsing(fn ($state) => $state . '%'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description),
            ])
            ->actions([
                Action::make('review')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.affiliate-fraud-signals.view', $record))
                    ->openUrlInNewTab(),

                Action::make('dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'dismissed'])),
            ])
            ->paginated(false)
            ->emptyStateHeading('No fraud alerts')
            ->emptyStateDescription('No suspicious activity detected.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
