<x-filament-panels::page>
    <div class="space-y-6">
        @forelse ($this->getSubscriptions() as $subscription)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-4">
                            <div class="rounded-lg bg-{{ $subscription->gatewayConfig()['color'] }}-50 p-3 dark:bg-{{ $subscription->gatewayConfig()['color'] }}-400/10">
                                <x-filament::icon
                                    :icon="$subscription->gatewayConfig()['icon']"
                                    class="h-6 w-6 text-{{ $subscription->gatewayConfig()['color'] }}-600 dark:text-{{ $subscription->gatewayConfig()['color'] }}-400"
                                />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ ucfirst($subscription->type) }} Subscription
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $subscription->planId }}
                                </p>
                                <div class="mt-2 flex items-center gap-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-{{ $subscription->status->color() }}-50 text-{{ $subscription->status->color() }}-700 ring-1 ring-inset ring-{{ $subscription->status->color() }}-600/20 dark:bg-{{ $subscription->status->color() }}-400/10 dark:text-{{ $subscription->status->color() }}-400 dark:ring-{{ $subscription->status->color() }}-400/30">
                                        {{ $subscription->status->label() }}
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        via {{ $subscription->gatewayConfig()['label'] }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ $subscription->formattedAmount() }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ $subscription->billingCycle() }}
                            </p>
                        </div>
                    </div>

                    @if ($subscription->nextBillingDate)
                        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Next billing: <span class="font-medium text-gray-900 dark:text-white">{{ $subscription->nextBillingDate->format('M d, Y') }}</span>
                            </p>
                        </div>
                    @endif

                    <div class="mt-4 flex gap-3">
                        @if ($subscription->status->isCancelable())
                            <x-filament::button
                                color="danger"
                                size="sm"
                                wire:click="cancelSubscription('{{ $subscription->gateway }}', '{{ $subscription->id }}')"
                                wire:confirm="{{ __('filament-cashier::subscriptions.actions.cancel_description') }}"
                            >
                                {{ __('filament-cashier::portal.subscriptions.cancel') }}
                            </x-filament::button>
                        @endif

                        @if ($subscription->status->isResumable())
                            <x-filament::button
                                color="success"
                                size="sm"
                                wire:click="resumeSubscription('{{ $subscription->gateway }}', '{{ $subscription->id }}')"
                            >
                                {{ __('filament-cashier::portal.subscriptions.resume') }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content p-12 text-center">
                    <x-filament::icon
                        icon="heroicon-o-credit-card"
                        class="mx-auto h-12 w-12 text-gray-400"
                    />
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('filament-cashier::portal.subscriptions.empty') }}
                    </h3>
                </div>
            </div>
        @endforelse

        @if ($this->hasMoreSubscriptions)
            <div class="flex justify-center">
                <x-filament::button
                    size="sm"
                    color="gray"
                    wire:click="loadMoreSubscriptions"
                >
                    {{ __('filament-cashier::portal.subscriptions.load_more') }}
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
