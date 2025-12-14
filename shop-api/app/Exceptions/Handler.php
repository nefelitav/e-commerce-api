<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof BadRequestException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Bad Request',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($e instanceof UnprocessableEntityException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Unprocessable Entity',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return parent::render($request, $e);
    }
}
