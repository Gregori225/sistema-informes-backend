<?php

namespace App\Utils;

class Response
{
    public static function json(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data, string $message = 'Operación exitosa', int $code = 200): void
    {
        self::json($code, [
            'status'  => 'success',
            'message' => $message,
            'data'    => $data
        ]);
    }

    public static function error(string $message, int $code = 400, mixed $details = null): void
    {
        self::json($code, [
            'status'  => 'error',
            'message' => $message,
            'details' => $details
        ]);
    }
}
