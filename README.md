# Nexum Chatbot

Multi-page chatbot marketing website with a PHP 8.2 admin panel, MongoDB Atlas storage, role-based access, and SMTP lead notifications.

The static `dist/` site remains deployable to GitHub Pages. The PHP application lives in `public/`; configure a PHP host with that directory as its document root.

## Admin features

- Contact leads and pipeline status
- Pricing packages shown on the website
- Editable hero and contact content
- Admin, editor, and read-only viewer accounts
- Audit records for sensitive changes

## Requirements

- PHP 8.2 or newer and Composer
- PHP extensions: `mongodb` 2.5+, `mbstring`, `openssl`, `ctype`, `filter`, and `json`
- MongoDB Atlas or a compatible MongoDB deployment
- SMTP credentials for lead notifications
- Apache with `mod_rewrite`, or an equivalent Nginx rewrite

## Local setup

```bash
composer install
cp .env.example .env
composer build-assets
composer init-db
php -S localhost:8080 -t public public/index.php
```

Fill in `.env` first. Generate long random values for `APP_KEY` and `APP_SETUP_TOKEN`; never commit `.env`.

Open `http://localhost:8080/setup`, enter the setup token, and create the first admin email and password. The setup route returns 404 after the first active administrator exists. Admin login is at `/admin/login`.

Passwords require at least 12 characters with uppercase, lowercase, a number, and a symbol. Five failed attempts lock an account for 15 minutes.

## Production deployment

1. Create a MongoDB Atlas database user and allow network access from the PHP host.
2. Upload the repository to a PHP 8.2 host.
3. Set the web document root to the repository's `public/` directory.
4. Create `.env` from `.env.example` on the server and add the Atlas and SMTP credentials.
5. Run `composer install --no-dev --optimize-autoloader`, `composer build-assets`, and `composer init-db`.
6. Ensure `storage/sessions` and `storage/logs` are writable by PHP.
7. Visit `/setup` once to create the first administrator, then sign in at `/admin/login`.
8. Force HTTPS and set `APP_ENV=production` and `APP_URL` to the final HTTPS origin.

For Nginx, route missing files to the front controller:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

The GitHub Pages workflow intentionally publishes `dist/` only. GitHub Pages cannot execute PHP, so the admin and MongoDB application needs a PHP-capable host.

## Security

- HttpOnly, SameSite sessions and Secure cookies in production
- CSRF protection on every mutation
- Server-side admin/editor/viewer permission checks
- Honeypot and per-IP rate limiting for contact submissions
- Password hashes only; no plain-text passwords
- Environment variables for database and SMTP secrets
