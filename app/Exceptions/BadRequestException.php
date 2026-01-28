<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class BadRequestException extends HttpException
{
    public function __construct(
        string $message = 'Bad Request',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            $message,
            $previous,
        );
    }
}
