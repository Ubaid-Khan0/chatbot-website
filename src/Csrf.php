<?php

declare(strict_types=1);

namespace Nexum;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(?string $provided = null): void
    {
        if ($provided === null) {
            $input = Http::input();
            $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['_csrf'] ?? '');
        }
        if (!is_string($provided) || !hash_equals(self::token(), $provided)) {
            throw new HttpException(419, 'The security token is invalid or expired.');
        }
    }
}
