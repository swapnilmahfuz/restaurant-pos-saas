# Restaurant POS SaaS — V2

Multi-restaurant, multi-branch, browser-based POS and operations management system. Phase 0 provides central administration, restaurant-scoped staff sign-in, and branch access control. Development is managed through Codex.

## Local development

Use PHP 8.4 or later in the PHP 8 series, Composer 2, and the PDO SQLite extension. Install PHP's ctype, cURL, DOM/XML, fileinfo, mbstring and OpenSSL extensions. A ZIP extension or archive utility is needed for Composer downloads. The Phase 0 interface uses Blade and plain CSS.

Run from the repository root on a fresh checkout.

```sh
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate
php artisan pos:create-admin admin@example.test
php artisan serve --host=127.0.0.1
```

The administrator command prompts for a password in the terminal. Keep the existing application key and database when resuming development.

1. Open `/central/login` and sign in as the central administrator.
2. Create a restaurant with its unique restaurant email. Add branches and a staff account, then select the branches that account may access.
3. In a separate browser session, open `/staff/login` and provide the restaurant email, staff email, and staff password. Open an assigned branch.

## Checks

```sh
composer test
vendor/bin/pint --test
composer validate
```

The feature tests use an in-memory SQLite database. See the [V2 product specification](docs/finalized-v2.md) and [Phase 0 technical specification](docs/phase-0-technical-spec.md).
