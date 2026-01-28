<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Events;

use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DocumentsDispatched
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string>  $documents
     */
    public function __construct(
        public readonly CheckoutSession $session,
        public readonly string $orderId,
        public readonly array $documents,
        public readonly string $queue,
    ) {}
}
