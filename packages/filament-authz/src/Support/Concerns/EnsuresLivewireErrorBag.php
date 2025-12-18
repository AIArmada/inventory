<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support\Concerns;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

use function Livewire\store;

trait EnsuresLivewireErrorBag
{
    public function getErrorBag(): MessageBag
    {
        $errorBag = store($this)->get('errorBag');

        if ($errorBag instanceof MessageBag) {
            return $errorBag;
        }

        $sharedErrors = app('view')->getShared()['errors'] ?? new ViewErrorBag();

        if ($sharedErrors instanceof ViewErrorBag) {
            $this->setErrorBag($sharedErrors->getMessages());
        } else {
            $this->setErrorBag([]);
        }

        $errorBag = store($this)->get('errorBag');

        return $errorBag instanceof MessageBag
            ? $errorBag
            : new MessageBag();
    }
}
