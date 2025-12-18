<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ServiceProvider;
use Livewire\ComponentHook;

final class EnsureLivewireErrorBagInitialized extends ComponentHook
{
    /**
     * @param  mixed  $view
     * @param  array<string, mixed>  $data
     */
    public function render($view, $data): void
    {
        $this->component->setErrorBag(new MessageBag());
    }

    /**
     * @param  mixed  $view
     * @param  array<string, mixed>  $data
     */
    public function renderIsland(string $name, $view, $data): void
    {
        $this->component->setErrorBag(new MessageBag());
    }
}

final class LivewireTestingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        app('livewire')->componentHook(EnsureLivewireErrorBagInitialized::class);
    }
}
