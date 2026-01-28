<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected static function success(ArrayableResponse $data, int $status = 200): JsonResponse
    {
        $arrayData = $data->toArray();

        return response()->json(
            array_merge(['success' => true], $arrayData),
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
