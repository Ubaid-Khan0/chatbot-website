<?php

declare(strict_types=1);

namespace Nexum;

final class Http
{
    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(parse_url(Config::get('APP_URL', ''), PHP_URL_PATH) ?: '', '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        return '/' . ltrim(rtrim($path, '/'), '/');
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $location, int $status = 302): never
    {
        header('Location: ' . $location, true, $status);
        exit;
    }

    public static function view(string $file, array $data = [], int $status = 200): never
    {
        http_response_code($status);
        extract($data, EXTR_SKIP);
        require $file;
        exit;
    }

    public static function ip(): string
    {
        return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 64);
    }

    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    }
}
