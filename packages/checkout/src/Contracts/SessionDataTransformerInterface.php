<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Checkout\Models\CheckoutSession;

interface SessionDataTransformerInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transform(array $data, CheckoutSession $session): array;
}
