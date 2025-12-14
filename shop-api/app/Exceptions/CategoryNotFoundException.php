<?php

namespace App\Exceptions;

use Exception;

class CategoryNotFoundException extends Exception
{
    public function __construct(int $id)
    {
        parent::__construct(
            "Category with id {$id} not found.",
            404
        );
    }
}
