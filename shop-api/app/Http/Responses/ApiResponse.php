<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Response;

trait ApiResponse
{
    protected static function success($data, int $status = 200): JsonResponse
    {
        $arrayData = is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : $data;

        return response()->json(
            array_merge(['success' => true,], $arrayData),
            $status
        );
    }

    protected function error(string $message = 'Error', int $status = 400,): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
