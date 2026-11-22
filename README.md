# Restaurant POS SaaS — V2

Multi-restaurant, multi-branch, browser-based POS and operations management system. Phase 0.5 adds restaurant and branch approvals, role-based module licensing, isolated demos and trials, and commercial audit history to the Phase 0 foundation. Development is managed through Codex.

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

## Commercial workflows

- From the staff sign-in page, submit a restaurant registration or open a one-hour sample demo. The demo can be reset from the staff workspace.
- In central administration, open **Review pending requests** to approve or reject restaurant registrations and owner branch requests.
- Open **Module registry** to control platform availability. Grant a module separately at the restaurant and branch level, then assign an eligible role and branch membership to the staff account.
- Use **Create private trial** for expiring credentials and selected sample-branch modules. Reset and conversion retire the old tenant and create a clean tenant with a new restaurant email.

## Checks

```sh
composer test
vendor/bin/pint --test
composer validate
```

The default feature tests use an in-memory SQLite database. See the [V2 master architecture](docs/finalized-v2.md), [commercial foundation specification](docs/phase-0-5-technical-spec.md), and [delivery roadmap](docs/roadmap.md).
