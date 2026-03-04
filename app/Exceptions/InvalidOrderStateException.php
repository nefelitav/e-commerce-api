<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class InvalidOrderStateException extends Exception
{
    public function __construct(string $reason)
    {
        parent::__construct($reason, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

