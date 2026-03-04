<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class CategoryAlreadyExistsException extends Exception
{
    public function __construct(string $name)
    {
        parent::__construct(
            "Category with name {$name} already exists.",
            Response::HTTP_CONFLICT,
        );
    }
}
