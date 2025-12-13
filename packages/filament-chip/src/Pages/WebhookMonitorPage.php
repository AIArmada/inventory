<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages;

use AIArmada\Chip\Data\WebhookHealth;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Webhooks\WebhookMonitor;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class WebhookMonitorPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Webhook Monitor';

    protected static ?string $title = 'Webhook Monitor';

    protected static ?string $slug = 'chip/webhook-monitor';

    protected static ?int $navigationSort = 100;

    public ?WebhookHealth $health = null;

    /** @var array<string, int> */
    public array $eventDistribution = [];

    /** @var array<string, int> */
    public array $failureBreakdown = [];

    public function mount(): void
    {
        $monitor = app(WebhookMonitor::class);

        $this->health = $monitor->getHealth();
        $this->eventDistribution = $monitor->getEventDistribution();
        $this->failureBreakdown = $monitor->getFailureBreakdown();
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-chip.navigation.group', 'Payments');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_failed')
                ->label('Retry Failed')
                ->icon(Heroicon::ArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $manager = app(WebhookRetryManager::class);
                    $retryable = $manager->getRetryableWebhooks()->take(10);

                    $succeeded = 0;
                    $failed = 0;

                    foreach ($retryable as $webhook) {
                        $result = $manager->retry($webhook);
                        if ($result->isSuccess()) {
                            $succeeded++;
                        } else {
                            $failed++;
                        }
                    }

                    Notification::make()
                        ->title('Retry Complete')
                        ->body("{$succeeded} succeeded, {$failed} failed")
                        ->success()
                        ->send();

                    $this->mount(); // Refresh data
                }),

            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::ArrowPath)
                ->action(fn () => $this->mount()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Webhook::query()
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->limit(8)
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processed' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('retry_count')
                    ->label('Retries')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('processing_time_ms')
                    ->label('Time (ms)')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(),

                TextColumn::make('last_error')
                    ->label('Error')
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s');
    }

    public function render(): View
    {
        return view('filament-chip::pages.webhook-monitor', [
            'health' => $this->health,
            'eventDistribution' => $this->eventDistribution,
            'failureBreakdown' => $this->failureBreakdown,
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
