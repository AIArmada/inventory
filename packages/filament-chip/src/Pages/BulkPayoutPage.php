<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages;

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Services\ChipSendService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Throwable;

class BulkPayoutPage extends Page implements HasForms
{
    use InteractsWithForms;

    /** @var array<int, array{bank_account_id: string, amount: float, description: string, reference: string, email: string}> */
    public array $payouts = [];

    /** @var array<int, array{reference: string, status: string, message: string}> */
    public array $results = [];

    public bool $hasProcessed = false;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Bulk Payouts';

    protected static ?string $title = 'Bulk Payouts';

    protected static ?string $slug = 'chip/payouts/bulk';

    protected static ?int $navigationSort = 51;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-chip.navigation.group', 'Payments');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Bulk Payout Instructions')
                    ->description('Create multiple payouts at once. Each payout will be processed via CHIP Send.')
                    ->schema([
                        Repeater::make('payouts')
                            ->label('Payouts')
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
                                    ->required(),

                                TextInput::make('amount')
                                    ->label('Amount (MYR)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->prefix('RM'),

                                TextInput::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('reference')
                                    ->label('Reference')
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('email')
                                    ->label('Notification Email')
                                    ->email()
                                    ->required(),
                            ])
                            ->columns(5)
                            ->addActionLabel('Add Payout')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(50),
                    ]),
            ])
            ->statePath('payouts');
    }

    public function processBulkPayouts(): void
    {
        $this->results = [];
        $this->hasProcessed = true;

        $service = app(ChipSendService::class);

        foreach ($this->payouts as $payout) {
            if (empty($payout['bank_account_id']) || empty($payout['amount'])) {
                continue;
            }

            $bankAccount = BankAccount::query()
                ->forOwner()
                ->whereKey($payout['bank_account_id'])
                ->first();

            if ($bankAccount === null) {
                $this->results[] = [
                    'reference' => $payout['reference'] ?? 'N/A',
                    'status' => 'error',
                    'message' => 'Selected bank account is not accessible for the current owner.',
                ];

                continue;
            }

            try {
                $amountInCents = (int) round((float) $payout['amount'] * 100);

                $instruction = $service->createSendInstruction(
                    amountInCents: $amountInCents,
                    currency: 'MYR',
                    recipientBankAccountId: (string) $payout['bank_account_id'],
                    description: $payout['description'] ?? '',
                    reference: $payout['reference'] ?? '',
                    email: $payout['email'] ?? '',
                );

                $this->results[] = [
                    'reference' => $payout['reference'] ?? 'N/A',
                    'status' => 'success',
                    'message' => sprintf('Created instruction ID: %s', $instruction->id ?? 'Unknown'),
                ];
            } catch (Throwable $e) {
                $this->results[] = [
                    'reference' => $payout['reference'] ?? 'N/A',
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $successCount = count(array_filter($this->results, fn ($r): bool => $r['status'] === 'success'));
        $failCount = count($this->results) - $successCount;

        if ($failCount === 0) {
            Notification::make()
                ->title('All payouts processed successfully')
                ->body(sprintf('%d payouts created', $successCount))
                ->success()
                ->send();
        } elseif ($successCount === 0) {
            Notification::make()
                ->title('All payouts failed')
                ->body(sprintf('%d payouts failed', $failCount))
                ->danger()
                ->send();
        } else {
            Notification::make()
                ->title('Bulk payouts partially processed')
                ->body(sprintf('%d succeeded, %d failed', $successCount, $failCount))
                ->warning()
                ->send();
        }
    }

    public function clearResults(): void
    {
        $this->results = [];
        $this->hasProcessed = false;
    }

    public function render(): View
    {
        return view('filament-chip::pages.bulk-payout', [
            'results' => $this->results,
            'hasProcessed' => $this->hasProcessed,
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('process')
                ->label('Process Payouts')
                ->icon(Heroicon::Play)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Process Bulk Payouts')
                ->modalDescription('Are you sure you want to process all payouts? This will create payout instructions via CHIP Send.')
                ->action(fn () => $this->processBulkPayouts()),

            Action::make('view_payouts')
                ->label('View All Payouts')
                ->icon(Heroicon::QueueList)
                ->color('info')
                ->url(fn (): string => route('filament.admin.resources.send-instructions.index')),
        ];
    }
}
