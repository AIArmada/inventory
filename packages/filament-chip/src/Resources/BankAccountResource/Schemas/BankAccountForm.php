<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Details')
                    ->description('Bank account information for receiving payouts.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Account Holder Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('The full name of the account holder as it appears on the bank account.'),

                        TextInput::make('account_number')
                            ->label('Account Number')
                            ->required()
                            ->maxLength(50)
                            ->helperText('The bank account number.'),

                        Select::make('bank_code')
                            ->label('Bank')
                            ->options(self::getMalaysianBanks())
                            ->searchable()
                            ->required()
                            ->helperText('Select the bank for this account.'),
                    ]),

                Section::make('Configuration')
                    ->description('Account capabilities and grouping.')
                    ->schema([
                        TextInput::make('reference')
                            ->label('Reference')
                            ->maxLength(100)
                            ->helperText('Your internal reference for this bank account.'),

                        TextInput::make('group_id')
                            ->label('Group ID')
                            ->numeric()
                            ->helperText('Optional group ID for organizing bank accounts.'),

                        Checkbox::make('is_debiting_account')
                            ->label('Enable for debiting')
                            ->helperText('Allow this account to be used for debiting funds.'),

                        Checkbox::make('is_crediting_account')
                            ->label('Enable for crediting')
                            ->default(true)
                            ->helperText('Allow this account to receive funds (payouts).'),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function getMalaysianBanks(): array
    {
        return [
            'MBBEMYKL' => 'Maybank',
            'CIABORJ' => 'CIMB Bank',
            'PABORJX' => 'Public Bank',
            'RHBAYJK' => 'RHB Bank',
            'HMABMYKL' => 'Hong Leong Bank',
            'AMMBMYKL' => 'AmBank',
            'UOVBMYKL' => 'UOB Malaysia',
            'OCBCMYKL' => 'OCBC Malaysia',
            'BKCHMYXX' => 'Bank of China Malaysia',
            'SCBLMYKX' => 'Standard Chartered Malaysia',
            'HSBCMYKL' => 'HSBC Malaysia',
            'BIMBMYKL' => 'Bank Islam',
            'BMMUMYKL' => 'Bank Muamalat',
            'AFBOMYKL' => 'Affin Bank',
            'AABORJ' => 'Alliance Bank',
            'AGOBMYKL' => 'Agrobank',
            'BSNAMYK1' => 'BSN',
            'RAKYATMY' => 'Bank Rakyat',
            'KABORJ' => 'Kenanga Investment Bank',
            'MBSABMYK' => 'MBSB Bank',
        ];
    }
}
