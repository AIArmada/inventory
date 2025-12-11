<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;
use AIArmada\Tax\Models\TaxExemption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxExemptionResource extends Resource
{
    protected static ?string $model = TaxExemption::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'certificate_number';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Customer Information')
                            ->schema([
                                Forms\Components\Select::make('exemptable_type')
                                    ->label('Entity Type')
                                    ->options([
                                        'AIArmada\\Customers\\Models\\Customer' => 'Customer',
                                        'AIArmada\\Customers\\Models\\CustomerGroup' => 'Customer Group',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('AIArmada\\Customers\\Models\\Customer'),

                                Forms\Components\Select::make('exemptable_id')
                                    ->label('Customer')
                                    ->searchable()
                                    ->required()
                                    ->options(function (Forms\Get $get) {
                                        $type = $get('exemptable_type');

                                        if ($type === 'AIArmada\\Customers\\Models\\Customer') {
                                            return \AIArmada\Customers\Models\Customer::query()
                                                ->get()
                                                ->mapWithKeys(fn ($c) => [$c->id => $c->full_name . ' (' . $c->email . ')']);
                                        }

                                        if ($type === 'AIArmada\\Customers\\Models\\CustomerGroup') {
                                            return \AIArmada\Customers\Models\CustomerGroup::pluck('name', 'id');
                                        }

                                        return [];
                                    }),

                                Forms\Components\Select::make('tax_zone_id')
                                    ->label('Tax Zone')
                                    ->relationship('taxZone', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Leave empty to apply exemption to all zones'),
                            ])
                            ->columns(3),

                        Forms\Components\Section::make('Certificate Details')
                            ->schema([
                                Forms\Components\TextInput::make('certificate_number')
                                    ->label('Certificate Number')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\FileUpload::make('certificate_file')
                                    ->label('Certificate Document')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(5120)
                                    ->disk('public')
                                    ->directory('tax-exemptions')
                                    ->downloadable()
                                    ->openable(),

                                Forms\Components\Textarea::make('reason')
                                    ->label('Exemption Reason')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Validity Period')
                            ->schema([
                                Forms\Components\DatePicker::make('starts_at')
                                    ->label('Effective From')
                                    ->default(now())
                                    ->native(false),

                                Forms\Components\DatePicker::make('expires_at')
                                    ->label('Expires At')
                                    ->native(false)
                                    ->after('starts_at')
                                    ->helperText('Leave blank for permanent exemption'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status')
                            ->schema([
                                Forms\Components\Toggle::make('is_verified')
                                    ->label('Verified')
                                    ->helperText('Mark as verified after review')
                                    ->default(false),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\Placeholder::make('status_info')
                                    ->label('Status')
                                    ->content(function ($record) {
                                        if (! $record) {
                                            return 'New exemption';
                                        }

                                        if ($record->expires_at && $record->expires_at->isPast()) {
                                            return '⚠️ Expired';
                                        }

                                        if ($record->expires_at && $record->expires_at->isBefore(now()->addDays(30))) {
                                            return '⏰ Expiring Soon';
                                        }

                                        if ($record->is_verified) {
                                            return '✅ Active & Verified';
                                        }

                                        return '⏳ Pending Verification';
                                    }),
                            ]),

                        Forms\Components\Section::make('Notes')
                            ->schema([
                                Forms\Components\Textarea::make('internal_notes')
                                    ->label('Internal Notes')
                                    ->rows(4)
                                    ->helperText('For internal use only'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('exemptable.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->exemptable_type === 'AIArmada\\Customers\\Models\\CustomerGroup' ? 'Group' : null),

                Tables\Columns\TextColumn::make('certificate_number')
                    ->label('Certificate #')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('taxZone.name')
                    ->label('Zone')
                    ->badge()
                    ->placeholder('All Zones')
                    ->color('info'),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Valid From')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('Never')
                    ->color(function ($record) {
                        if (! $record->expires_at) {
                            return 'success';
                        }

                        if ($record->expires_at->isPast()) {
                            return 'danger';
                        }

                        if ($record->expires_at->isBefore(now()->addDays(30))) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->icon(function ($record) {
                        if (! $record->expires_at) {
                            return 'heroicon-o-infinity';
                        }

                        if ($record->expires_at->isPast()) {
                            return 'heroicon-o-x-circle';
                        }

                        if ($record->expires_at->isBefore(now()->addDays(30))) {
                            return 'heroicon-o-exclamation-triangle';
                        }

                        return 'heroicon-o-check-circle';
                    }),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('expires_at', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('tax_zone_id')
                    ->label('Zone')
                    ->relationship('taxZone', 'name'),

                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring in 30 days')
                    ->query(
                        fn ($query) => $query
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>=', now())
                            ->where('expires_at', '<=', now()->addDays(30))
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(
                        fn ($query) => $query
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<', now())
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('verify')
                        ->label('Verify')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn ($record) => ! $record->is_verified)
                        ->requiresConfirmation()
                        ->action(fn ($record) => $record->update(['is_verified' => true]))
                        ->successNotificationTitle('Exemption verified'),
                    Tables\Actions\Action::make('renew')
                        ->label('Renew')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->form([
                            Forms\Components\DatePicker::make('new_expires_at')
                                ->label('New Expiry Date')
                                ->required()
                                ->native(false)
                                ->after('today'),
                        ])
                        ->action(function ($record, array $data): void {
                            $record->update(['expires_at' => $data['new_expires_at']]);
                        })
                        ->successNotificationTitle('Exemption renewed'),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('verify')
                        ->label('Verify Selected')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_verified' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxExemptions::route('/'),
            'create' => Pages\CreateTaxExemption::route('/create'),
            'view' => Pages\ViewTaxExemption::route('/{record}'),
            'edit' => Pages\EditTaxExemption::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $expiring = static::getModel()::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        return $expiring > 0 ? (string) $expiring : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
