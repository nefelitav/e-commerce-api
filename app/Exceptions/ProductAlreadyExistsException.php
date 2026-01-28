<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class ProductAlreadyExistsException extends Exception
{
    public function __construct(string $name)
    {
        parent::__construct(
            "Product with name {$name} already exists.",
            Response::HTTP_CONFLICT,
        );
    }
}
