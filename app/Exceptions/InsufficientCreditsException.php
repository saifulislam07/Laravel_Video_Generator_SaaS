<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(string $message = 'You have no credits left. Buy a package to keep rendering.')
    {
        parent::__construct($message);
    }
}
