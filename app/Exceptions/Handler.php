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

        if ($e instanceof ProductNotFoundException
            || $e instanceof OrderNotFoundException
            || $e instanceof CategoryNotFoundException
        ) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Not Found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($e instanceof ProductAlreadyExistsException
            || $e instanceof CategoryAlreadyExistsException
        ) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Conflict',
            ], Response::HTTP_CONFLICT);
        }

        if ($e instanceof UnprocessableEntityException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Unprocessable Entity',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($e instanceof InsufficientStockException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Insufficient Stock',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($e instanceof InvalidOrderStateException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'Invalid Order State',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return parent::render($request, $e);
    }
}
