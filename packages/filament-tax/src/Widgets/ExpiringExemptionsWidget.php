<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Support\TaxOwnerScope;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringExemptionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Expiring Exemptions (30 Days)';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('exemptable.full_name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('certificate_number')
                    ->label('Certificate #')
                    ->searchable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(30),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('d M Y')
                    ->description(fn (TaxExemption $record): string => $record->expires_at?->diffForHumans() ?? '')
                    ->color('warning'),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No expiring exemptions')
            ->emptyStateDescription('All exemptions are valid for more than 30 days.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /**
     * @return Builder<TaxExemption>
     */
    protected function getTableQuery(): Builder
    {
        $now = CarbonImmutable::now();

        return TaxOwnerScope::applyToOwnedQuery(TaxExemption::query())
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now->addDays(30))
            ->where('expires_at', '>=', $now)
            ->where('status', 'approved')
            ->orderBy('expires_at');
    }
}
