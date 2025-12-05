<?php

declare(strict_types=1);

namespace AIArmada\Chip\Exceptions;

use Exception;

class NoRecurringTokenException extends Exception
{
    public function __construct(string $message = 'No recurring token available')
    {
        parent::__construct($message);
    }
}
