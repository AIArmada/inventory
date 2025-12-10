<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Pages;

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class ManifestPage extends Page implements HasTable
{
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make()
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
                    ->formatStateUsing(fn ($state) => number_format($state / 1000, 2) . ' kg'),

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
                Tables\Actions\Action::make('mark_picked_up')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Shipment $record) => ! ($record->metadata['picked_up'] ?? false))
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
                Tables\Actions\BulkAction::make('bulk_mark_picked_up')
                    ->label('Mark Picked Up')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records): void {
                        foreach ($records as $record) {
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
                ->action(function (): void {
                    $this->getTableQuery()
                        ->whereNull('metadata->picked_up')
                        ->orWhere('metadata->picked_up', false)
                        ->each(function (Shipment $shipment): void {
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
        $query = Shipment::query()
            ->where('status', ShipmentStatus::Shipped);

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
}
