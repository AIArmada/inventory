<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Schemas;

use AIArmada\Chip\Models\BankAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SendInstructionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recipient')
                    ->description('Select the bank account to send the payout to.')
                    ->schema([
                        Select::make('bank_account_id')
                            ->label('Bank Account')
                            ->options(function (): array {
                                return BankAccount::query()
                                    ->forOwner()
                                    ->whereIn('status', ['active', 'approved'])
                                    ->get()
                                    ->mapWithKeys(fn (BankAccount $account): array => [
                                        $account->id => sprintf(
                                            '%s - %s (%s)',
                                            $account->name ?? 'Unknown',
                                            $account->account_number ?? 'N/A',
                                            $account->bank_code ?? 'N/A'
                                        ),
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Only verified bank accounts are available.'),
                    ]),

                Section::make('Payment Details')
                    ->description('Specify the payout amount and details.')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount (MYR)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('RM')
                            ->helperText('Enter the amount in MYR.'),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A description for this payout (visible to recipient).'),

                        TextInput::make('reference')
                            ->label('Reference')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Your internal reference for this payout.'),

                        TextInput::make('email')
                            ->label('Notification Email')
                            ->email()
                            ->required()
                            ->helperText('Email address to notify about the payout status.'),
                    ]),
            ]);
    }
}
