<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers;

use AIArmada\Orders\Models\Order;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'orderNotes';

    protected static ?string $title = 'Notes';

    private function getOrderRecordOrNull(): ?Order
    {
        if (! isset($this->ownerRecord)) {
            return null;
        }

        $record = $this->getOwnerRecord();

        return $record instanceof Order ? $record : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('Note')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_customer_visible')
                    ->label('Visible to Customer')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('content')
                    ->label('Note')
                    ->wrap()
                    ->limit(100),

                Tables\Columns\IconColumn::make('is_customer_visible')
                    ->label('Customer Visible')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_customer_visible')
                    ->label('Visibility')
                    ->trueLabel('Customer Visible')
                    ->falseLabel('Internal Only'),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->authorize(function (): bool {
                        $user = Filament::auth()->user();

                        $order = $this->getOrderRecordOrNull();

                        if ($user === null || $order === null) {
                            return false;
                        }

                        return Gate::forUser($user)->allows('addNote', $order);
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $userId = Filament::auth()->id();

                        $data['user_id'] = $userId ? (string) $userId : null;

                        return $data;
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->authorize(function (): bool {
                        $user = Filament::auth()->user();

                        $order = $this->getOrderRecordOrNull();

                        if ($user === null || $order === null) {
                            return false;
                        }

                        return Gate::forUser($user)->allows('update', $order);
                    }),
                \Filament\Actions\DeleteAction::make()
                    ->authorize(function (): bool {
                        $user = Filament::auth()->user();

                        $order = $this->getOrderRecordOrNull();

                        if ($user === null || $order === null) {
                            return false;
                        }

                        return Gate::forUser($user)->allows('update', $order);
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->authorize(function (): bool {
                            $user = Filament::auth()->user();

                            $order = $this->getOrderRecordOrNull();

                            if ($user === null || $order === null) {
                                return false;
                            }

                            return Gate::forUser($user)->allows('update', $order);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
