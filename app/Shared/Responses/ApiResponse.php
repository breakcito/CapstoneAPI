<?php

namespace App\Shared\Responses;

/**
 * Clase para respuestas API estandarizadas.
 */
class ApiResponse
{
    public static function success($data = null, ?string $message = null)
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
        ];
    }

    public static function error($message = null, $errors = null)
    {
        return [
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
        ];
    }
}
