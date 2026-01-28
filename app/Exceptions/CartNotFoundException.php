<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class CartNotFoundException extends Exception
{
    public function __construct(int $id)
    {
        parent::__construct(
            "Cart with id {$id} not found.",
            Response::HTTP_NOT_FOUND,
        );
    }
}
