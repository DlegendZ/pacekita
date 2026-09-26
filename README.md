# PaceKita

PaceKita is a Laravel 13, Blade, PostgreSQL-ready running-club website built from `../Running_Club_Website_SRD.docx`. It covers public run and club discovery, email-verified accounts, profiles, memberships, organizer scheduling, RSVP/waitlist, in-site and email notices, attendance, reporting, and admin audit.

## Quick start

Requirements: PHP 8.3+, Composer 2, Node.js 20+, npm, and PostgreSQL 15+ (or SQLite for a local demo). Run commands from `pacekita`.

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Set `DB_*` values in `.env` for PostgreSQL, create the database, then:

```sh
php artisan migrate
php artisan storage:link
php artisan db:seed
npm run build
php artisan serve
```

For an SQLite demo, set `DB_CONNECTION=sqlite` and `DB_DATABASE=/absolute/path/to/database/database.sqlite` in `.env`, create the empty database file, then migrate and seed. Seeder runs only in local environment. Demo organizer: `demo@pacekita.test` / `password12345`; change password or delete account before using real data. Mail uses log driver by default; verification links appear in `storage/logs/laravel.log`. Run `php artisan queue:work` and `php artisan schedule:work` in separate terminals for notifications, recurrence, and reminders.

Docker alternative: after `composer install`, `npm ci`, `.env` creation, `php artisan key:generate`, and `npm run build`, run `docker compose up --build -d` then `docker compose exec web php artisan migrate --seed`. Open `http://localhost:8000`. Compose uses a local-only PostgreSQL password; do not reuse it in staging or production. Stop services with `docker compose down` (without `-v` if you want to keep demo data).

## Test

```sh
php artisan test
npm run build
```

Tests use in-memory SQLite. Production reference uses PostgreSQL to serialize competing seat claims through `lockForUpdate` on occurrence rows. Run concurrency/load tests against PostgreSQL before release; SQLite does not validate row-lock behavior.

## Production checklist

- Set unique `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, PostgreSQL, mail, queue, and session secrets outside source control.
- Build assets, migrate with backup/rollback plan, run one queue worker and one scheduler, and monitor failed jobs/backups.
- Configure HTTPS, secure cookies, private file access, email provider, retention/deletion terms, safety/incident policy, and local legal review before launch.
- Verify responsive/keyboard accessibility and load targets; SRD’s 100-concurrent-user search target is not yet benchmarked.

No separate SPA or API service is needed. `routes/web.php` uses session auth and CSRF; domain mutations live in `app/Services`, scoped permissions in `app/Policies`.
