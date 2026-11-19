<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function make(int $status, string $message, mixed $data = null, ?array $errors = null, ?string $errorMessage = null): JsonResponse
    {
        return response()->json([
            'statusCode' => $status, 'message' => $message, 'dataResponse' => $data,
            'errorMessageDTO' => $status < 400 ? null : array_filter([
                'statusCode' => $status, 'message' => $errorMessage ?? $message, 'errors' => $errors,
            ], fn ($value) => $value !== null),
        ], $status)->header('Cache-Control', 'no-store');
    }
}
