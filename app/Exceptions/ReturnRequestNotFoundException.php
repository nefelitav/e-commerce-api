<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class ReturnRequestNotFoundException extends Exception
{
    public function __construct(int $id)
    {
        parent::__construct(
            "Return request with id {$id} not found.",
            Response::HTTP_NOT_FOUND,
        );
    }
}
