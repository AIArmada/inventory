<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\FilamentAffiliates\Concerns\InteractsWithAffiliate;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class PortalPayouts extends Page implements HasTable
{
    use InteractsWithAffiliate;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament-affiliates::pages.portal.payouts';

    public static function getNavigationLabel(): string
    {
        return __('Payouts');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Payout History');
    }

    public function table(Table $table): Table
    {
        $affiliate = $this->getAffiliate();

        return $table
            ->query(
                AffiliatePayout::query()
                    ->when($affiliate, fn (Builder $query) => $query
                        ->where('owner_type', $affiliate->getMorphClass())
                        ->where('owner_id', $affiliate->getKey()))
                    ->when(! $affiliate, fn (Builder $query) => $query->whereRaw('1 = 0'))
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('reference')
                    ->label(__('Reference'))
                    ->searchable(),

                TextColumn::make('amount_minor')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn ($state) => $this->formatAmount((int) $state))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(function (string | BackedEnum $state): string {
                        $value = $state instanceof BackedEnum ? $state->value : $state;

                        return match ($value) {
                            PayoutStatus::Completed->value => 'success',
                            PayoutStatus::Pending->value => 'warning',
                            PayoutStatus::Processing->value => 'info',
                            PayoutStatus::Failed->value => 'danger',
                            PayoutStatus::Cancelled->value => 'gray',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('paid_at')
                    ->label(__('Paid At'))
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();
        $totalPaid = $affiliate
            ? (int) AffiliatePayout::query()
                ->where('owner_type', $affiliate->getMorphClass())
                ->where('owner_id', $affiliate->getKey())
                ->where('status', PayoutStatus::Completed)
                ->sum('total_minor')
            : 0;

        return [
            'hasAffiliate' => $this->hasAffiliate(),
            'totalPaid' => $totalPaid,
            'pendingEarnings' => $this->getPendingEarnings(),
        ];
    }
}
