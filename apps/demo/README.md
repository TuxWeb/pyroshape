# Pyroshape Demo

The demo uses the monorepo's `tuxweb-it/pyroshape` package to show authenticated Projects and Tasks CRUD. The Slim/PHP-DI backend handles auth, API routes, persistence, logging, and SPA fallback. The SvelteKit frontend builds to `public/app`.

## Install

Run these commands from `apps/demo` with PHP 8.5 or later, Composer, and Node.js 24 or later.

```bash
composer install
npm --prefix frontend ci
npm --prefix frontend run build
```

Composer links the framework from the monorepo root via the relative `../..` path repository.

## Run locally

Create a local MySQL or MariaDB database named `pyroshape_demo` and a dedicated application account. Grant the application account SELECT, INSERT, UPDATE, and DELETE on that database. Apply the migrations in order using an account with schema privileges.

```bash
mysql -h 127.0.0.1 -u MIGRATION_USER -p pyroshape_demo < database/migrations/001_auth_schema.sql
mysql -h 127.0.0.1 -u MIGRATION_USER -p pyroshape_demo < database/migrations/002_projects_tasks_schema.sql
```

In Bash, set the runtime environment for the PHP process. Replace `pyroshape_demo` in `PYROSHAPE_DB_USER` with the application account name.

```bash
export PYROSHAPE_ENVIRONMENT=development
export PYROSHAPE_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=pyroshape_demo;charset=utf8mb4'
export PYROSHAPE_DB_USER=pyroshape_demo
read -rsp 'Demo database password: ' PYROSHAPE_DB_PASSWORD
printf '\n'
export PYROSHAPE_DB_PASSWORD

for name in PYROSHAPE_JWT_ACCESS_KEY PYROSHAPE_JWT_REFRESH_KEY PYROSHAPE_COOKIE_KEY PYROSHAPE_COOKIE_SALT; do
    export "$name=$(php -r 'echo bin2hex(random_bytes(32));')"
done

php -S 127.0.0.1:8080 -t public public/index.php
```

Open [the local demo](http://127.0.0.1:8080/). Register, follow the development verification link, and log in to create a project and manage its tasks.

The four auth variables each require at least 32 bytes. Keep the same values for the lifetime of any sessions you want to preserve. When `PYROSHAPE_ENVIRONMENT` is unset, the app uses production settings, requires HTTPS for its session cookie, and omits verification tokens from API responses. The development verification link and HTTP cookie are intended for a loopback server.

## Verify

Run these checks from `apps/demo`. The PHP tests use isolated fixtures and require `pdo_sqlite`. The SPA test requires the frontend build.

```bash
composer validate
npm --prefix frontend run check
npm --prefix frontend run build

(
    for test in tests/*.php; do
        php "$test" || exit 1
    done
)
```
