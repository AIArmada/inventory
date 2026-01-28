<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Transformers;

use AIArmada\Checkout\Contracts\SessionDataTransformerInterface;
use AIArmada\Checkout\Models\CheckoutSession;

final class NullSessionDataTransformer implements SessionDataTransformerInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transform(array $data, CheckoutSession $session): array
    {
        return $data;
    }
}
