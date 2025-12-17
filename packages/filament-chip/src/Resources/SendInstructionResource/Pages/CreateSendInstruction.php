<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Pages;

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\FilamentChip\Resources\SendInstructionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Override;
use Throwable;

final class CreateSendInstruction extends CreateRecord
{
    protected static string $resource = SendInstructionResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Create Payout';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Send a payout to a verified bank account via CHIP Send.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = app(ChipSendService::class);

        $bankAccount = BankAccount::query()
            ->forOwner()
            ->whereKey($data['bank_account_id'] ?? null)
            ->first();

        if ($bankAccount === null) {
            Notification::make()
                ->title('Invalid bank account')
                ->body('Selected bank account is not accessible for the current owner.')
                ->danger()
                ->send();

            $this->halt();

            return $data;
        }

        try {
            $amountInCents = (int) round((float) $data['amount'] * 100);

            $instruction = $service->createSendInstruction(
                amountInCents: $amountInCents,
                currency: 'MYR',
                recipientBankAccountId: (string) $data['bank_account_id'],
                description: $data['description'],
                reference: $data['reference'],
                email: $data['email'],
            );

            Notification::make()
                ->title('Payout created successfully')
                ->body(sprintf('Instruction ID: %s', $instruction->id ?? 'Unknown'))
                ->success()
                ->send();

            return array_merge($data, [
                'id' => $instruction->id ?? null,
                'state' => $instruction->state ?? 'queued',
            ]);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Failed to create payout')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();

            return $data;
        }
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
