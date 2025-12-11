<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Models\PermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PermissionRequestResource extends Resource
{
    protected static ?string $model = PermissionRequest::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Approval Requests';

    protected static string | UnitEnum | null $navigationGroup = 'Authorization';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->schema([
                        Forms\Components\Select::make('requester_id')
                            ->label('Requester')
                            ->relationship('requester', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('requested_permissions')
                            ->label('Requested Permissions')
                            ->placeholder('Enter comma-separated permissions')
                            ->helperText('e.g., user.view, user.edit'),

                        Forms\Components\TextInput::make('requested_roles')
                            ->label('Requested Roles')
                            ->placeholder('Enter comma-separated roles')
                            ->helperText('e.g., Admin, Editor'),

                        Forms\Components\Textarea::make('justification')
                            ->label('Justification')
                            ->rows(3)
                            ->required()
                            ->placeholder('Why is this access needed?'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->helperText('Leave empty for permanent access'),
                    ]),

                Forms\Components\Section::make('Approval Details')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'denied' => 'Denied',
                                'expired' => 'Expired',
                            ])
                            ->required()
                            ->default('pending'),

                        Forms\Components\Select::make('approver_id')
                            ->label('Approver')
                            ->relationship('approver', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Textarea::make('approval_notes')
                            ->label('Approval Notes')
                            ->rows(2),
                    ])
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->limit(8),

                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('requested_permissions')
                    ->label('Permissions')
                    ->badge()
                    ->separator(',')
                    ->limit(3),

                Tables\Columns\TextColumn::make('requested_roles')
                    ->label('Roles')
                    ->badge()
                    ->color('info')
                    ->separator(',')
                    ->limit(3),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'denied' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Approver')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'denied' => 'Denied',
                        'expired' => 'Expired',
                    ]),

                Tables\Filters\Filter::make('pending_only')
                    ->label('Pending Only')
                    ->query(fn (Builder $query) => $query->where('status', 'pending'))
                    ->toggle(),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon')
                    ->query(
                        fn (Builder $query) => $query
                            ->where('status', 'approved')
                            ->where('expires_at', '<=', now()->addDays(7))
                            ->where('expires_at', '>', now())
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Approval Notes')
                            ->rows(2),
                    ])
                    ->action(function (PermissionRequest $record, array $data): void {
                        $record->approve(auth()->user(), $data['notes'] ?? null);
                    })
                    ->visible(fn (PermissionRequest $record) => $record->isPending()),

                Tables\Actions\Action::make('deny')
                    ->label('Deny')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Denial Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (PermissionRequest $record, array $data): void {
                        $record->deny(auth()->user(), $data['reason']);
                    })
                    ->visible(fn (PermissionRequest $record) => $record->isPending()),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_all')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    $record->approve(auth()->user());
                                }
                            }
                        }),

                    Tables\Actions\BulkAction::make('deny_all')
                        ->label('Deny Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Denial Reason')
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    $record->deny(auth()->user(), $data['reason']);
                                }
                            }
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissionRequests::route('/'),
            'create' => Pages\CreatePermissionRequest::route('/create'),
            'view' => Pages\ViewPermissionRequest::route('/{record}'),
            'edit' => Pages\EditPermissionRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return config('filament-authz.enterprise.approvals.enabled', false);
    }
}
