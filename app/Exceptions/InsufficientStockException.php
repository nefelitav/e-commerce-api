<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class InsufficientStockException extends Exception
{
    public function __construct(int $productId, int $requested, int $available)
    {
        parent::__construct(
            "Insufficient stock for product {$productId}: requested {$requested}, available {$available}.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}

