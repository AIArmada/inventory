<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Tables;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class VouchersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('usages'))
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Tag),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(static fn (VoucherType | string $state): string => match ($state instanceof VoucherType ? $state : VoucherType::from($state)) {
                        VoucherType::Percentage => 'primary',
                        VoucherType::Fixed => 'success',
                        VoucherType::FreeShipping => 'warning',
                        VoucherType::BuyXGetY => 'info',
                        VoucherType::Tiered => 'secondary',
                        VoucherType::Bundle => 'primary',
                        VoucherType::Cashback => 'success',
                    })
                    ->formatStateUsing(static fn (VoucherType | string $state): string => $state instanceof VoucherType ? $state->label() : VoucherType::from($state)->label())
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(static function ($state, Voucher $record): string {
                        $rawType = $record->type;
                        $type = $rawType instanceof VoucherType ? $rawType : VoucherType::from((string) $rawType);

                        if ($type === VoucherType::Percentage) {
                            return MoneyHelper::formatPercentage((int) $state);
                        }

                        return MoneyHelper::formatMoney((int) $state, (string) $record->currency);
                    })
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('condition_target_display')
                    ->label('Target')
                    ->state(static function (Voucher $record): string {
                        $metadata = $record->metadata ?? [];
                        $definition = $record->target_definition
                            ?? (is_array($metadata) ? ($metadata['target_definition'] ?? null) : null);
                        $dsl = $definition !== null
                            ? ConditionTarget::from($definition)->toDsl()
                            : ConditionTargetPreset::default()->dsl();
                        $preset = ConditionTargetPreset::detect($dsl);

                        return ($preset ?? ConditionTargetPreset::Custom)->label();
                    })
                    ->tooltip(static function (Voucher $record): ?string {
                        $metadata = $record->metadata ?? [];
                        $definition = $record->target_definition
                            ?? (is_array($metadata) ? ($metadata['target_definition'] ?? null) : null);

                        return $definition !== null
                            ? ConditionTarget::from($definition)->toDsl()
                            : ConditionTargetPreset::default()->dsl();
                    })
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('usages_count')
                    ->label('Redeemed')
                    ->counts('usages')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('applied_count')
                    ->label('Applied')
                    ->alignCenter()
                    ->sortable()
                    ->tooltip('Number of times this voucher has been applied to carts'),

                TextColumn::make('usageProgress')
                    ->label('Usage %')
                    ->state(fn (Voucher $record): ?float => $record->usageProgress)
                    ->formatStateUsing(static fn (?float $state): string => $state === null ? '—' : number_format($state, 1) . '%')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('remaining_uses')
                    ->label('Remaining')
                    ->state(fn (Voucher $record): string => Str::of((string) ($record->getRemainingUses() ?? '∞'))->toString())
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('owner_display_name')
                    ->label('Owner')
                    ->placeholder('Global')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(static fn (VoucherStatus | string $state): string => match ($state instanceof VoucherStatus ? $state : VoucherStatus::from($state)) {
                        VoucherStatus::Active => 'success',
                        VoucherStatus::Paused => 'warning',
                        VoucherStatus::Expired => 'danger',
                        VoucherStatus::Depleted => 'gray',
                    })
                    ->formatStateUsing(static fn (VoucherStatus | string $state): string => $state instanceof VoucherStatus ? $state->label() : VoucherStatus::from($state)->label())
                    ->sortable(),

                IconColumn::make('allows_manual_redemption')
                    ->label('Manual?')
                    ->boolean()
                    ->tooltip('Allow staff to redeem this voucher manually'),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Voucher Type')
                    ->options(static fn (): array => collect(VoucherType::cases())->mapWithKeys(fn (VoucherType $type): array => [$type->value => $type->label()])->toArray()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static fn (): array => collect(VoucherStatus::cases())->mapWithKeys(fn (VoucherStatus $status): array => [$status->value => $status->label()])->toArray()),

                Filter::make('manual_only')
                    ->label('Manual Redemption')
                    ->query(static fn ($query) => $query->where('allows_manual_redemption', true)),

                Filter::make('active_now')
                    ->label('Active right now')
                    ->query(static function ($query) {
                        $now = now();

                        return $query
                            ->where('status', VoucherStatus::Active)
                            ->where(function ($builder) use ($now): void {
                                $builder
                                    ->whereNull('starts_at')
                                    ->orWhere('starts_at', '<=', $now);
                            })
                            ->where(function ($builder) use ($now): void {
                                $builder
                                    ->whereNull('expires_at')
                                    ->orWhere('expires_at', '>=', $now);
                            });
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                DeleteAction::make()
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll(static function () {
                $interval = config('filament-vouchers.polling_interval');

                if ($interval === null || $interval === '') {
                    return null;
                }

                return is_numeric($interval) ? $interval . 's' : (string) $interval;
            })
            ->paginated([25, 50, 100])
            ->striped();
    }
}
