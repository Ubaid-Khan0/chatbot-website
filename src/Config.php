<?php

declare(strict_types=1);

namespace Nexum;

final class Config
{
    private static bool $loaded = false;

    public static function load(string $root): void
    {
        if (self::$loaded) {
            return;
        }

        $file = $root . DIRECTORY_SEPARATOR . '.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $value = trim($value, "\"'");
                if (getenv($key) === false) {
                    putenv($key . '=' . $value);
                    $_ENV[$key] = $value;
                }
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null || trim($value) === '') {
            throw new \RuntimeException("Missing required environment variable: {$key}");
        }
        return $value;
    }

    public static function int(string $key, int $default): int
    {
        return (int) (self::get($key, (string) $default) ?? $default);
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'production') === 'production';
    }
}
