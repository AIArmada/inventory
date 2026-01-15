<x-filament-panels::page>
    <div class="space-y-6">
        @if($subscriptions->isEmpty())
            <x-filament::section>
                <div class="text-center py-6">
                    <x-heroicon-o-credit-card class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No subscriptions') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('You currently have no active subscriptions.') }}</p>
                </div>
            </x-filament::section>
        @else
            @foreach($subscriptions as $subscription)
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            {{ $subscription->type ?? __('Subscription') }}
                            @if($subscription->active())
                                <x-filament::badge color="success" size="sm">
                                    {{ __('Active') }}
                                </x-filament::badge>
                            @elseif($subscription->onTrial())
                                <x-filament::badge color="warning" size="sm">
                                    {{ __('Trial') }}
                                </x-filament::badge>
                            @elseif($subscription->onGracePeriod())
                                <x-filament::badge color="warning" size="sm">
                                    {{ __('Grace Period') }}
                                </x-filament::badge>
                            @elseif($subscription->cancelled())
                                <x-filament::badge color="danger" size="sm">
                                    {{ __('Cancelled') }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </x-slot>

                    <div class="space-y-4">
                        {{-- Subscription Details --}}
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('Started') }}
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $subscription->created_at->format('M d, Y') }}
                                </dd>
                            </div>

                            @if($subscription->onTrial())
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('Trial Ends') }}
                                    </dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $subscription->trial_ends_at->format('M d, Y') }}
                                    </dd>
                                </div>
                            @endif

                            @if($subscription->ends_at)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('Ends At') }}
                                    </dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $subscription->ends_at->format('M d, Y') }}
                                    </dd>
                                </div>
                            @endif

                            @if($subscription->chip_price)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('Plan') }}
                                    </dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $subscription->chip_price }}
                                    </dd>
                                </div>
                            @endif
                        </dl>

                        {{-- Subscription Actions --}}
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            @if($subscription->active() && !$subscription->onGracePeriod())
                                <x-filament::button
                                    color="danger"
                                    size="sm"
                                    wire:click="cancelSubscription('{{ $subscription->id }}')"
                                    wire:confirm="{{ __('Are you sure you want to cancel this subscription? You will still have access until the end of your billing period.') }}"
                                >
                                    {{ __('Cancel Subscription') }}
                                </x-filament::button>
                            @endif

                            @if($subscription->onGracePeriod())
                                <x-filament::button
                                    color="success"
                                    size="sm"
                                    wire:click="resumeSubscription('{{ $subscription->id }}')"
                                >
                                    {{ __('Resume Subscription') }}
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
