<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    /**
     * Return a standardized success response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $statusCode
     * @return JsonResponse
     */
    protected function respondSuccess($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'code' => $statusCode,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Return a standardized error response.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @param  mixed  $errors
     * @return JsonResponse
     */
    protected function respondError(string $message = 'Error', int $statusCode = 400, $errors = null): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'code' => $statusCode,
            'message' => $message
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Log error with context
     *
     * @param string $operation
     * @param Exception $exception
     * @param array $context
     */
    public function logError(string $operation, Exception $exception, array $context = []): void
    {
        Log::error("EBM Error: {$operation}", array_merge($context, [
            'error_message' => $exception->getMessage(),
            'error_trace' => $exception->getTraceAsString(),
            'timestamp' => Carbon::now()->toISOString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]));
    }

    /**
     * Log activity with context
     *
     * @param string $activity
     * @param array $context
     */
    public function logActivity(string $activity, array $context = []): void
    {
        Log::info("EBM Activity: {$activity}", array_merge($context, [
            'timestamp' => Carbon::now()->toISOString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]));
    }

    /**
     * Build cache key with parameters
     *
     * @param string $operation
     * @param array $params
     * @return string
     */
    private function buildCacheKey(string $prefix, string $operation, array $params = []): string
    {
        $key = $prefix . $operation;
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        return $key;
    }
}
