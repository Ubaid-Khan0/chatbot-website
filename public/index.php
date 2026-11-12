<?php

declare(strict_types=1);

use Nexum\Auth;
use Nexum\Config;
use Nexum\Csrf;
use Nexum\Http;
use Nexum\HttpException;
use Nexum\Mailer;
use Nexum\SiteRenderer;
use Nexum\Store;

$root = require dirname(__DIR__) . '/src/bootstrap.php';
$store = new Store();
$renderer = new SiteRenderer($root, $store);
$path = Http::path();
$method = Http::method();
$isApi = str_starts_with($path, '/api/');

try {
    $pages = ['/' => 'index', '/features' => 'features', '/solutions' => 'solutions', '/pricing' => 'pricing', '/contact' => 'contact'];
    if ($method === 'GET' && isset($pages[$path])) {
        header('Content-Type: text/html; charset=utf-8');
        echo $renderer->publicPage($pages[$path]);
        exit;
    }

    if ($method === 'POST' && $path === '/api/leads') {
        $input = Http::input();
        if (!empty($input['website'])) {
            Http::json(['ok' => true], 201);
        }
        $lead = $store->createLead($input);
        try {
            (new Mailer())->sendLeadNotification($lead);
            $store->markLeadNotification($lead['id'], 'sent');
        } catch (Throwable $error) {
            $store->markLeadNotification($lead['id'], 'failed', $error->getMessage());
            error_log('Lead email notification failed: ' . $error->getMessage());
        }
        Http::json(['ok' => true, 'message' => 'Thanks. Your enquiry has been received.'], 201);
    }

    if ($path === '/setup') {
        if (Auth::hasAdmin()) {
            throw new HttpException(404, 'Setup is no longer available.');
        }
        if ($method === 'GET') {
            Http::view($root . '/views/setup.php', ['csrf' => Csrf::token()]);
        }
        Csrf::verify((string) (Http::input()['_csrf'] ?? ''));
        $input = Http::input();
        if (!hash_equals(Config::require('APP_SETUP_TOKEN'), (string) ($input['setup_token'] ?? ''))) {
            throw new HttpException(403, 'Invalid setup token.');
        }
        Auth::createFirstAdmin((string) ($input['name'] ?? ''), (string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''));
        Http::redirect('/admin/login?created=1');
    }

    if ($path === '/admin/login') {
        if ($method === 'GET') {
            if (Auth::user()) {
                Http::redirect('/admin');
            }
            Http::view($root . '/views/login.php', ['csrf' => Csrf::token(), 'created' => isset($_GET['created'])]);
        }
        Csrf::verify((string) (Http::input()['_csrf'] ?? ''));
        $input = Http::input();
        if (!Auth::attempt((string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''))) {
            Http::view($root . '/views/login.php', ['csrf' => Csrf::token(), 'created' => false, 'error' => 'Email or password is incorrect, or the account is temporarily locked.'], 422);
        }
        Http::redirect('/admin');
    }

    if ($method === 'POST' && $path === '/admin/logout') {
        Csrf::verify((string) (Http::input()['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
        Auth::logout();
        Http::json(['ok' => true]);
    }

    if ($method === 'GET' && $path === '/admin') {
        try {
            $user = Auth::requireUser();
        } catch (HttpException $error) {
            if ($error->status === 401) {
                Http::redirect('/admin/login');
            }
            throw $error;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $renderer->adminPage($user);
        exit;
    }

    if ($isApi) {
        $user = Auth::requireUser();
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            Csrf::verify((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        }
        if ($method === 'GET' && $path === '/api/admin/dashboard') Http::json(['data' => $store->dashboard(), 'user' => $user]);
        if ($method === 'GET' && $path === '/api/admin/leads') Http::json($store->listLeads($_GET));
        if ($method === 'POST' && $path === '/api/admin/leads') {
            Auth::requireUser(['admin', 'editor']);
            Http::json(['data' => $store->createLead(Http::input())], 201);
        }
        if ($method === 'PATCH' && preg_match('#^/api/admin/leads/([a-f0-9]{24})$#', $path, $match)) Http::json(['data' => $store->updateLead($match[1], Http::input(), Auth::requireUser(['admin', 'editor']))]);
        if ($method === 'GET' && $path === '/api/admin/plans') Http::json(['data' => $store->plans()]);
        if ($method === 'PUT' && $path === '/api/admin/plans') Http::json(['data' => $store->savePlans((array) (Http::input()['plans'] ?? []), Auth::requireUser(['admin', 'editor']))]);
        if ($method === 'GET' && $path === '/api/admin/content') Http::json(['data' => $store->content()]);
        if ($method === 'PUT' && $path === '/api/admin/content') Http::json(['data' => $store->saveContent((array) (Http::input()['content'] ?? []), Auth::requireUser(['admin', 'editor']))]);
        if ($method === 'GET' && $path === '/api/admin/users') {
            Auth::requireUser(['admin']);
            Http::json(['data' => $store->users()]);
        }
        if ($method === 'POST' && $path === '/api/admin/users') Http::json(['data' => $store->createUser(Http::input(), Auth::requireUser(['admin']))], 201);
        if ($method === 'PATCH' && preg_match('#^/api/admin/users/([a-f0-9]{24})$#', $path, $match)) Http::json(['data' => $store->updateUser($match[1], Http::input(), Auth::requireUser(['admin']))]);
        if ($method === 'GET' && $path === '/api/admin/audit') {
            Auth::requireUser(['admin']);
            Http::json(['data' => $store->auditLog((int) ($_GET['limit'] ?? 100))]);
        }
    }

    throw new HttpException(404, 'Page not found.');
} catch (HttpException $error) {
    if ($isApi) {
        Http::json(['error' => $error->getMessage()], $error->status);
    }
    Http::view($root . '/views/error.php', ['status' => $error->status, 'message' => $error->getMessage()], $error->status);
} catch (Throwable $error) {
    error_log((string) $error);
    $message = Config::isProduction() ? 'The server could not complete this request.' : $error->getMessage();
    if ($isApi) {
        Http::json(['error' => $message], 500);
    }
    Http::view($root . '/views/error.php', ['status' => 500, 'message' => $message], 500);
}
