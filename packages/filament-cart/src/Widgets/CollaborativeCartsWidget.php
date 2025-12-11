<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use Akaunting\Money\Money;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Widget showing active collaborative carts.
 *
 * Displays shared carts with multiple collaborators,
 * showing activity and collaboration status.
 */
final class CollaborativeCartsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Collaborative Carts';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Cart ID')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('owner')
                    ->label('Owner')
                    ->getStateUsing(fn (Cart $record): string => $this->getOwner($record)),

                Tables\Columns\TextColumn::make('collaborator_count')
                    ->label('Collaborators')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 5 => 'success',
                        $state >= 2 => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('value')
                    ->label('Cart Value')
                    ->getStateUsing(fn (Cart $record): string => $this->getCartValue($record)),

                Tables\Columns\TextColumn::make('activity_status')
                    ->label('Activity')
                    ->getStateUsing(fn (Cart $record): string => $this->getActivityStatus($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active Now' => 'success',
                        'Recent' => 'info',
                        'Idle' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('last_activity_at', 'desc')
            ->actions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Cart $record): string => route('filament.admin.resources.carts.view', $record)),

                Action::make('view_collaborators')
                    ->label('Collaborators')
                    ->icon('heroicon-o-user-group')
                    ->modalHeading('Cart Collaborators')
                    ->modalContent(fn (Cart $record) => view('filament-cart::widgets.collaborators-modal', [
                        'cart' => $record,
                        'collaborators' => $this->getCollaborators($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->emptyStateHeading('No collaborative carts')
            ->emptyStateDescription('No carts are currently being shared between collaborators.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<Cart>
     */
    protected function getTableQuery(): Builder
    {
        return Cart::query()
            ->where('is_collaborative', true)
            ->where('collaborator_count', '>', 0)
            ->where('updated_at', '>=', now()->subDays(30));
    }

    private function getOwner(Cart $record): string
    {
        $metadata = $record->metadata ?? [];

        return $metadata['owner_email'] ?? $metadata['customer_email'] ?? $record->identifier;
    }

    private function getCartValue(Cart $record): string
    {
        $currency = mb_strtoupper($record->currency ?: config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($record->subtotal);
    }

    private function getActivityStatus(Cart $record): string
    {
        if (! $record->last_activity_at) {
            return 'Unknown';
        }

        $minutesSinceActivity = now()->diffInMinutes($record->last_activity_at);

        if ($minutesSinceActivity < 5) {
            return 'Active Now';
        }

        if ($minutesSinceActivity < 60) {
            return 'Recent';
        }

        return 'Idle';
    }

    /**
     * Get collaborators from cart metadata.
     *
     * @return array<int, array{email: string, role: string, joined_at: string|null}>
     */
    private function getCollaborators(Cart $record): array
    {
        $metadata = $record->metadata ?? [];
        $collaborators = $metadata['collaborators'] ?? [];

        if (! is_array($collaborators)) {
            return [];
        }

        return array_map(function ($collaborator): array {
            return [
                'email' => $collaborator['email'] ?? 'Unknown',
                'role' => $collaborator['role'] ?? 'viewer',
                'joined_at' => $collaborator['joined_at'] ?? null,
            ];
        }, $collaborators);
    }
}
