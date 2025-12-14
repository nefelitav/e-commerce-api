<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UnprocessableEntityException extends HttpException
{
    public function __construct(string $message = 'Unprocessable Entity', \Throwable $previous = null)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $previous);
    }

    public static function fromException(\Throwable $e): self
    {
        return new self($e->getMessage(), $e);
    }
}
