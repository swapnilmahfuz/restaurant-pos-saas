# Phase 0 technical specification

## Development stack

Laravel 13 on PHP 8.4 with Composer-locked dependencies, Blade, and SQLite for local development and tests. The application uses a shared relational database with explicit tenant keys. Framework tables support server-side sessions, cache and throttling, and future background jobs.

## Data ownership

| Table | Ownership and constraints |
| --- | --- |
| `restaurants` | Root tenant. Its normalized email is globally unique. |
| `branches` | Belongs to one restaurant. Branch names are unique within that restaurant. |
| `staff` | Belongs to one restaurant. Its normalized email is unique within that restaurant. |
| `branch_staff` | Unique restaurant, staff, branch tuple. Composite foreign keys require both referenced rows to belong to the named restaurant. |
| `central_admins` | Central identity with a globally unique normalized email in its own table. |

Email normalization lowercases and trims values at the provisioning and sign-in boundaries. Database foreign key enforcement must stay enabled. Branch and staff identifiers are global primary keys. Identifier possession grants no privileges.

## Browser routes and authorization

| Entry point | Authorization |
| --- | --- |
| `GET/POST /staff/login` | Sign-in checks the restaurant email, staff email, password, and both active flags. |
| `GET /staff`, `GET /staff/branches/{branch}` | Active staff in an active restaurant. Branch data is queried through the authenticated staff member's active branch assignments. An unavailable branch returns 404. |
| `GET/POST /central/login` | Sign-in accepts active central administrators from a separate identity provider. |
| `/central/restaurants/…` | Active central administrator. Nested staff and branch bindings are scoped to the parent restaurant. Assignment updates validate ownership and save in one transaction. |
| `POST /logout` | Laravel request forgery protection. Clears both guard sessions and rotates the session and CSRF token. |

A successful sign-in rotates the session ID and clears the other guard. Failed credential lookups use a common message. Sign-in POST requests are limited to five attempts per identity and IP per minute, and 30 per IP per minute for each sign-in surface. Passwords are hashed with bcrypt using Laravel's hashing service. Central administrators provision initial staff passwords and the bootstrap command accepts administrator passwords through a hidden prompt.

Sessions are encrypted and server-side by default. Browser forms use Laravel's request forgery protection. Operational responses use `Cache-Control: no-store, private` and a restrictive Content Security Policy. The application has no client-side scripts or persistence. Browser actions render from live server responses.

## Hosting settings

Serve only the `public/` directory and use HTTPS. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the HTTPS origin, and `SESSION_SECURE_COOKIE=true`. Keep the application key and database credentials in environment secrets. Use shared server-side session and cache stores with atomic locks for a multi-instance deployment. Phase 0 validation targets the documented SQLite development configuration.
