<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Events;

use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CheckoutPaymentCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function __construct(
        public readonly CheckoutSession $session,
        public readonly array $paymentData = [],
    ) {}
}
