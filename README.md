# Pyroshape Demo SPA

Small Slim/PHP-DI demo app that demonstrates a Pyroshape-driven SPA using the reusable `dam2k/pyroshape-core` toolkit.

The demo flow covers Projects and Tasks: register or sign in, open the projects area, create a project, and manage tasks inside the project detail view. The PHP backend owns auth, API routes, persistence, access logging, and SPA fallback routing; the SvelteKit frontend builds to static assets served from `public/app`.

## Layout

- `public/index.php` - PHP entrypoint.
- `src/App.php` - app wrapper over `Dam2k\Pyroshape\Core\App`.
- `config/config.php` - app settings.
- `frontend/` - SvelteKit SPA source for the Projects + Tasks workflow.
- `tests/` - smoke, auth, API, identity, access logging, SPA fallback, and package checks.
- `/home/dino/projects/pyroshape-core` - reusable toolkit package.

## Install

```bash
php /home/dino/temp/prove/php/composer.phar install
npm --prefix frontend install
```

The app uses a Composer path repository:

```json
{
  "type": "path",
  "url": "../../../pyroshape-core",
  "options": {
    "symlink": true
  }
}
```

This worktree expects `/home/dino/projects/pyroshape-core` at `../../../pyroshape-core` relative to the app root.

## Runtime

Build the SPA, configure the app in `config/config.php`, prepare the database, then serve `public/index.php` through PHP-FPM/Apache/Nginx or the local PHP server.

The default runtime config uses MySQL:

```php
mysql:host=127.0.0.1;port=3306;dbname=pyroshape;charset=utf8mb4
```

Create the database and user to match `config/config.php`, or update that file with your local MySQL DSN and credentials. For the default DSN, one local setup is:

```bash
mysql -h127.0.0.1 -uroot -p -e "CREATE DATABASE IF NOT EXISTS pyroshape CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -h127.0.0.1 -uroot -p -e "CREATE USER IF NOT EXISTS 'pyroshape'@'127.0.0.1' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON pyroshape.* TO 'pyroshape'@'127.0.0.1'"
```

Apply the schema migrations before serving the app:

```bash
mysql -h127.0.0.1 -uroot -p pyroshape < database/migrations/001_auth_schema.sql
mysql -h127.0.0.1 -uroot -p pyroshape < database/migrations/002_projects_tasks_schema.sql
```

Then build and serve the app:

```bash
npm --prefix frontend run build
php -S 127.0.0.1:8080 -t public
```

Open:

```text
http://127.0.0.1:8080/
```

The PHP backend serves API routes and the built static SPA from `public/app`. Do not leave the local PHP server running after manual testing.

## Frontend

```bash
npm --prefix frontend install
npm --prefix frontend run check
npm --prefix frontend run build
```

The build writes static files into `public/app`.

`public/app` is generated output and is ignored by git.

## Verification

Run the core route hook check from `/home/dino/projects/pyroshape-core`:

```bash
php tests/app_routes_before_spa_fallback.php
```

Run the demo checks from this app root:

```bash
php /home/dino/temp/prove/php/composer.phar validate
php tests/core_package.php
php tests/auth_flow.php
php tests/auth_service.php
php tests/token_cookie.php
php tests/project_api.php
npm --prefix frontend run check
npm --prefix frontend run build
php tests/spa_fallback.php
php tests/smoke.php
php tests/access_logger.php
php tests/demo_identity.php
```

The auth, token cookie, project API, access logger, and identity checks use SQLite-backed test configuration where applicable. If a local MySQL-backed check is added later and fails because MySQL is not configured, record the exact failure and continue with the SQLite-backed checks above.

## Core Toolkit

Reusable backend code lives in sibling package:

```text
/home/dino/projects/pyroshape-core
```

Package name:

```text
dam2k/pyroshape-core
```

Use this app as a demo shell. Put reusable Slim/PHP-DI/auth/logging/database behavior in `pyroshape-core`; keep project-specific code in this app under `src/`.
