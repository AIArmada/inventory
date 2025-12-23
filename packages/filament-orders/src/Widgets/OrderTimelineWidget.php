<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Widgets;

use AIArmada\Orders\Models\Order;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Throwable;

class OrderTimelineWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    public ?Order $record = null;

    public ?array $noteData = [];

    protected string $view = 'filament-orders::widgets.order-timeline';

    protected int | string | array $columnSpan = 'full';

    public function mount(Order $record): void
    {
        $this->record = $record;
    }

    public function getTimelineEvents(): Collection
    {
        if (! $this->record) {
            return collect([]);
        }

        $this->record->loadMissing([
            'customer',
            'payments',
            'orderNotes.user',
        ]);

        $events = collect([]);

        // Order created event
        $events->push([
            'type' => 'created',
            'title' => 'Order Created',
            'description' => 'Order was placed by ' . ($this->record->customer?->full_name ?? 'Guest'),
            'icon' => 'heroicon-o-shopping-cart',
            'color' => 'success',
            'timestamp' => $this->record->created_at,
        ]);

        // Status transitions from activity log (if using spatie/laravel-activitylog)
        if (method_exists($this->record, 'activities')) {
            try {
                /** @var Collection<int, object> $activities */
                $activities = $this->record->activities()->latest()->limit(25)->get();

                foreach ($activities as $activity) {
                    /** @var object{description: string, properties: array{old_status?: string, new_status?: string}, created_at: \Carbon\Carbon, causer?: object{name: string}|null} $activity */
                    if ($activity->description !== 'status_changed') {
                        continue;
                    }

                    $events->push([
                        'type' => 'status_change',
                        'title' => 'Status Updated',
                        'description' => sprintf(
                            'Status changed from %s to %s',
                            $activity->properties['old_status'] ?? 'Unknown',
                            $activity->properties['new_status'] ?? 'Unknown'
                        ),
                        'icon' => 'heroicon-o-arrow-path',
                        'color' => 'info',
                        'timestamp' => $activity->created_at,
                        'causer' => $activity->causer?->name ?? 'System',
                    ]);
                }
            } catch (Throwable) {
                // Ignore activity log if not configured.
            }
        }

        // Payment events
        foreach ($this->record->payments ?? [] as $payment) {
            $currency = $this->record->currency ?? (string) config('orders.currency.default', 'MYR');

            $events->push([
                'type' => 'payment',
                'title' => 'Payment ' . ucfirst($payment->status),
                'description' => sprintf(
                    '%s payment of %s via %s',
                    ucfirst($payment->status),
                    $currency . ' ' . number_format($payment->amount / 100, 2),
                    $payment->gateway
                ),
                'icon' => $payment->status === 'completed' ? 'heroicon-o-check-circle' : 'heroicon-o-credit-card',
                'color' => $payment->status === 'completed' ? 'success' : 'warning',
                'timestamp' => $payment->created_at,
            ]);
        }

        // Shipment events
        if ($this->record->shipped_at) {
            $events->push([
                'type' => 'shipped',
                'title' => 'Order Shipped',
                'description' => sprintf(
                    'Shipped via %s (Tracking: %s)',
                    $this->record->shipping_carrier ?? 'Unknown',
                    $this->record->tracking_number ?? 'N/A'
                ),
                'icon' => 'heroicon-o-truck',
                'color' => 'info',
                'timestamp' => $this->record->shipped_at,
            ]);
        }

        // Notes
        foreach ($this->record->orderNotes as $note) {
            $events->push([
                'type' => 'note',
                'title' => 'Note Added',
                'description' => $note->content,
                'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                'color' => 'gray',
                'timestamp' => $note->created_at,
                'causer' => $note->user?->name ?? 'System',
            ]);
        }

        return $events->sortByDesc('timestamp')->values();
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('Note')
                    ->required()
                    ->rows(2)
                    ->placeholder('Add a note to this order timeline...'),

                Forms\Components\Toggle::make('is_customer_visible')
                    ->label('Visible to Customer')
                    ->default(false)
                    ->helperText('Customer will see this note in their order history'),
            ])
            ->statePath('noteData');
    }

    public function addNote(): void
    {
        $data = $this->form->getState();

        if (! $this->record) {
            return;
        }

        $user = Filament::auth()->user();

        if (! $user || ! Gate::forUser($user)->allows('addNote', $this->record)) {
            Notification::make()
                ->title('Not authorized')
                ->danger()
                ->send();

            return;
        }

        try {
            $this->record->orderNotes()->create([
                'content' => $data['content'],
                'is_customer_visible' => $data['is_customer_visible'] ?? false,
                'user_id' => Filament::auth()->id(),
            ]);
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to add note')
                ->body('Please try again. If the problem persists, contact support.')
                ->danger()
                ->send();

            return;
        }

        $this->noteData = [];
        $this->form->fill();

        $this->dispatch('note-added');

        Notification::make()
            ->title('Note added successfully')
            ->success()
            ->send();
    }
}
