<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class CategoryNotFoundException extends Exception
{
    public function __construct(int $id)
    {
        parent::__construct(
            "Category with id {$id} not found.",
            Response::HTTP_NOT_FOUND,
        );
    }
}
