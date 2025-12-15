<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Bad Request', \Throwable $previous = null)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message, $previous);
    }

    public static function fromException(\Throwable $e): self
    {
        return new self($e->getMessage(), $e);
    }
}
