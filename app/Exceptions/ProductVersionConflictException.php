<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

final class ProductVersionConflictException extends Exception
{
    public function __construct(int $id, int $expected, int $actual)
    {
        parent::__construct(
            "Product {$id} was modified by someone else (expected version {$expected}, current version {$actual}). Reload and retry.",
            Response::HTTP_CONFLICT,
        );
    }
}
