<?php

namespace App\Exceptions;

use Exception;

class TripOverlapException extends Exception
{
    public function __construct(string $message = 'Trip overlaps with existing trip', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
