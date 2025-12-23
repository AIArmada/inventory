<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

class ManifestPage extends Page implements HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?string $selectedCarrier = null;

    public ?string $manifestDate = null;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected string $view = 'filament-shipping::pages.manifest';

    protected static ?string $slug = 'shipping-manifests';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return 'Manifests';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return 'Shipping';
    }

    public function getTitle(): string
    {
        return 'Shipping Manifests';
    }

    public function mount(): void
    {
        $this->manifestDate = Carbon::today()->toDateString();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make()
                    ->schema([
                        Forms\Components\Select::make('selectedCarrier')
                            ->label('Carrier')
                            ->options(fn () => $this->getCarrierOptions())
                            ->placeholder('All Carriers')
                            ->live(),

                        Forms\Components\DatePicker::make('manifestDate')
                            ->label('Manifest Date')
                            ->default(today())
                            ->required()
                            ->live(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        $weightUnit = (string) config('shipping.defaults.weight_unit', 'g');

        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tracking_number')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('carrier_code')
                    ->label('Carrier')
                    ->badge(),

                Tables\Columns\TextColumn::make('destination_address')
                    ->label('Destination')
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state['city'] ?? '') . ', ' . ($state['state'] ?? '')
                        : '-'),

                Tables\Columns\TextColumn::make('total_weight')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state === null
                        ? '-'
                        : ($weightUnit === 'kg'
                            ? number_format($state / 1000, 2) . ' kg'
                            : number_format($state) . ' g')),

                Tables\Columns\IconColumn::make('picked_up')
                    ->label('Picked Up')
                    ->getStateUsing(fn (Shipment $record) => $record->metadata['picked_up'] ?? false)
                    ->boolean(),

                Tables\Columns\TextColumn::make('shipped_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('mark_picked_up')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Shipment $record) => ! ($record->metadata['picked_up'] ?? false))
                    ->authorize(fn (Shipment $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Shipment $record): void {
                        $record->update([
                            'metadata' => array_merge($record->metadata ?? [], [
                                'picked_up' => true,
                                'picked_up_at' => now()->toDateTimeString(),
                            ]),
                        ]);

                        Notification::make()
                            ->title('Marked as Picked Up')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulk_mark_picked_up')
                    ->label('Mark Picked Up')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->authorize(fn (): bool => auth()->user()?->can('shipping.shipments.update') ?? false)
                    ->action(function ($records): void {
                        $user = auth()->user();

                        if ($user === null) {
                            return;
                        }

                        foreach ($records as $record) {
                            if (! $record instanceof Shipment) {
                                continue;
                            }

                            if (! $user->can('update', $record)) {
                                continue;
                            }

                            $record->update([
                                'metadata' => array_merge($record->metadata ?? [], [
                                    'picked_up' => true,
                                    'picked_up_at' => now()->toDateTimeString(),
                                ]),
                            ]);
                        }

                        Notification::make()
                            ->title('Shipments Marked as Picked Up')
                            ->body(count($records) . ' shipment(s) updated.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('shipped_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_manifest')
                ->label('Generate Manifest PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->action(function (): void {
                    // In a real implementation, this would generate a PDF manifest
                    Notification::make()
                        ->title('Manifest Generated')
                        ->body('The manifest PDF has been generated.')
                        ->success()
                        ->send();
                }),

            Action::make('mark_all_picked_up')
                ->label('Mark All Picked Up')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->authorize(fn (): bool => auth()->user()?->can('shipping.shipments.update') ?? false)
                ->action(function (): void {
                    $user = auth()->user();

                    if ($user === null) {
                        return;
                    }

                    $query = $this->getTableQuery()
                        ->where(function (Builder $builder): void {
                            $builder
                                ->whereNull('metadata->picked_up')
                                ->orWhere('metadata->picked_up', false);
                        });

                    $query->each(function (Shipment $shipment) use ($user): void {
                        if (! $user->can('update', $shipment)) {
                            return;
                        }

                        $shipment->update([
                            'metadata' => array_merge($shipment->metadata ?? [], [
                                'picked_up' => true,
                                'picked_up_at' => now()->toDateTimeString(),
                            ]),
                        ]);
                    });

                    Notification::make()
                        ->title('All Shipments Marked as Picked Up')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return Builder<Shipment>
     */
    protected function getTableQuery(): Builder
    {
        $query = Shipment::query();

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return $query->whereRaw('0 = 1');
            }

            $query->forOwner($owner, includeGlobal: true);
        }

        $query->where('status', ShipmentStatus::Shipped);

        if ($this->manifestDate !== null) {
            $query->whereDate('shipped_at', $this->manifestDate);
        }

        if ($this->selectedCarrier !== null) {
            $query->where('carrier_code', $this->selectedCarrier);
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    protected function getCarrierOptions(): array
    {
        $shipping = app(ShippingManager::class);

        return collect($shipping->getAvailableDrivers())
            ->mapWithKeys(fn ($driver) => [$driver => ucfirst($driver)])
            ->toArray();
    }

    private function resolveOwner(): ?Model
    {
        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
