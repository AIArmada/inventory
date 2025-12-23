<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class CustomerAlreadyCreated extends Exception
{
    /**
     * Create a new CustomerAlreadyCreated exception.
     */
    public static function exists(Model $owner): static
    {
        $chipId = method_exists($owner, 'chipId') ? $owner->chipId() : null;

        return new static(
            class_basename($owner) . ' is already a CHIP customer with ID ' . ($chipId ?: 'unknown') . '.'
        );
    }
}
