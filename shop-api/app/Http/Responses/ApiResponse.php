<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Response;

trait ApiResponse
{
    protected static function success(Response $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data->toArray(),
        ], $status);
    }

    protected function error(string $message = 'Error', int $status = 400,): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
