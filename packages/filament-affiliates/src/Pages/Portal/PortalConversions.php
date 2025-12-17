<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\AffiliateConversion;
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

class PortalConversions extends Page implements HasTable
{
    use InteractsWithAffiliate;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament-affiliates::pages.portal.conversions';

    public static function getNavigationLabel(): string
    {
        return __('Conversions');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Conversion History');
    }

    public function table(Table $table): Table
    {
        $affiliate = $this->getAffiliate();

        return $table
            ->query(
                AffiliateConversion::query()
                    ->when($affiliate, fn (Builder $query) => $query->where('affiliate_id', $affiliate->getKey()))
                    ->when(! $affiliate, fn (Builder $query) => $query->whereRaw('1 = 0'))
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('order_reference')
                    ->label(__('Order Reference'))
                    ->searchable(),

                TextColumn::make('total_minor')
                    ->label(__('Order Total'))
                    ->formatStateUsing(fn ($state) => $this->formatAmount((int) $state))
                    ->sortable(),

                TextColumn::make('commission_minor')
                    ->label(__('Commission'))
                    ->formatStateUsing(fn ($state) => $this->formatAmount((int) $state))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(function (string | BackedEnum $state): string {
                        $value = $state instanceof BackedEnum ? $state->value : $state;

                        return match ($value) {
                            ConversionStatus::Approved->value => 'success',
                            ConversionStatus::Pending->value => 'warning',
                            ConversionStatus::Rejected->value => 'danger',
                            ConversionStatus::Qualified->value => 'info',
                            ConversionStatus::Paid->value => 'success',
                            default => 'gray',
                        };
                    }),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'hasAffiliate' => $this->hasAffiliate(),
            'totalConversions' => $this->getTotalConversions(),
            'totalEarnings' => $this->getTotalEarnings(),
            'pendingEarnings' => $this->getPendingEarnings(),
        ];
    }
}
