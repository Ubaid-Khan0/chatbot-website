<?php

declare(strict_types=1);

namespace Nexum;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class Auth
{
    private const ROLES = ['admin', 'editor', 'viewer'];

    public static function hasAdmin(): bool
    {
        return Database::connection()->users->countDocuments(['role' => 'admin', 'active' => true], ['limit' => 1]) > 0;
    }

    public static function createFirstAdmin(string $name, string $email, string $password): string
    {
        if (self::hasAdmin()) {
            throw new HttpException(404, 'Setup is no longer available.');
        }
        self::validatePassword($password);
        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new HttpException(422, 'Name must be between 2 and 100 characters.');
        }
        $now = new UTCDateTime();
        $result = Database::connection()->users->insertOne([
            'name' => $name,
            'email' => self::normalizeEmail($email),
            'password_hash' => password_hash($password, self::passwordAlgorithm()),
            'role' => 'admin',
            'active' => true,
            'failed_attempts' => 0,
            'locked_until' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (string) $result->getInsertedId();
    }

    public static function attempt(string $email, string $password): bool
    {
        $collection = Database::connection()->users;
        $normalizedEmail = self::normalizeEmail($email);
        $user = $collection->findOne(['email' => $normalizedEmail]);
        if (!$user) {
            usleep(250000);
            return false;
        }

        $lockedUntil = $user['locked_until'] ?? null;
        if ($lockedUntil instanceof UTCDateTime && $lockedUntil->toDateTime()->getTimestamp() > time()) {
            return false;
        }

        if (($user['active'] ?? false) !== true || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            $attempts = ((int) ($user['failed_attempts'] ?? 0)) + 1;
            $update = ['failed_attempts' => $attempts, 'updated_at' => new UTCDateTime()];
            if ($attempts >= 5) {
                $update['locked_until'] = new UTCDateTime((time() + 900) * 1000);
                $update['failed_attempts'] = 0;
            }
            $collection->updateOne(['_id' => $user['_id']], ['$set' => $update]);
            return false;
        }

        $collection->updateOne(['_id' => $user['_id']], ['$set' => [
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ]]);
        session_regenerate_id(true);
        $_SESSION['auth'] = [
            'id' => (string) $user['_id'],
            'email' => (string) $user['email'],
            'name' => (string) ($user['name'] ?? 'Administrator'),
            'role' => (string) $user['role'],
            'authenticated_at' => time(),
        ];
        Csrf::token();
        return true;
    }

    public static function user(): ?array
    {
        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth) || empty($auth['id'])) {
            return null;
        }
        $lifetime = Config::int('SESSION_LIFETIME', 7200);
        if ((int) ($auth['authenticated_at'] ?? 0) + $lifetime < time()) {
            self::logout();
            return null;
        }
        $user = Database::connection()->users->findOne(['_id' => new ObjectId((string) $auth['id']), 'active' => true]);
        if (!$user) {
            self::logout();
            return null;
        }
        $_SESSION['auth']['authenticated_at'] = time();
        return array_merge($auth, ['role' => (string) $user['role'], 'name' => (string) $user['name']]);
    }

    public static function requireUser(array $roles = self::ROLES): array
    {
        $user = self::user();
        if (!$user) {
            throw new HttpException(401, 'Authentication required.');
        }
        if (!in_array($user['role'], $roles, true)) {
            throw new HttpException(403, 'You do not have permission to perform this action.');
        }
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function passwordAlgorithm(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function validatePassword(string $password): void
    {
        if (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z\d]/', $password)) {
            throw new HttpException(422, 'Password must be at least 12 characters and include uppercase, lowercase, number, and symbol.');
        }
    }

    public static function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'A valid email address is required.');
        }
        return $normalized;
    }
}
