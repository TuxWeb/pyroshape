# Pyroshape Skeleton

Start a Slim/PHP-DI app with PostgreSQL and a Svelte 5, SvelteKit 3, TypeScript, and shadcn-svelte frontend. You can also use [MariaDB](#mariadb-or-mysql) or [another frontend](#using-another-frontend).

## Layout

- `public/index.php` - PHP entrypoint.
- `src/App.php` - app wrapper over `TuxWeb\Pyroshape\Core\App`.
- `config/config.php` - app settings.
- `frontend/` - SvelteKit SPA with shadcn-svelte component source.
- `tests/` - smoke and auth checks.
- `AGENTS.md` - shared working conventions for coding agents. `CLAUDE.md` imports the same file for Claude Code.
- `SKILL.md` - detailed Pyroshape delivery workflow.

## Quick start

Use PHP 8.5+, Composer, Node.js 24, and PostgreSQL for the default setup. Run the commands from the starter directory or a [standalone copy](#using-the-starter-for-a-separate-app).

```bash
composer install
npm --prefix frontend ci
npm --prefix frontend run check
npm --prefix frontend run build
```

The monorepo installs `tuxweb-it/pyroshape` from the root through a Composer path repository. A standalone copy installs it from Packagist. The frontend build writes to `public/app`. Complete [local authentication setup](#local-authentication), then start the server in the same shell.

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Open [the app](http://127.0.0.1:8080). To check the backend, run `curl -fsS http://127.0.0.1:8080/health`. To verify authentication, register, follow the verification link shown in development, sign in, and sign out.

### Local authentication

Choose one database and apply its migration to an empty development database. Keep each app's database and credentials separate.

#### PostgreSQL (default)

Enable PHP's `pdo_pgsql` driver. As a local PostgreSQL administrator, create a dedicated login with a development password and a database owned by that login. If you already have a dedicated local database and login, use their names in the commands below.

```bash
createuser --pwprompt pyroshape_app
createdb --owner=pyroshape_app pyroshape_app
```

From the app directory, apply the schema as the app login. `psql` prompts for the database password if required by your PostgreSQL configuration.

```bash
psql --host=127.0.0.1 --username=pyroshape_app --dbname=pyroshape_app \
  --set=ON_ERROR_STOP=1 --single-transaction \
  --file=database/migrations/postgresql/001_auth_schema.sql
export PYROSHAPE_DB_DSN='pgsql:host=127.0.0.1;port=5432;dbname=pyroshape_app'
export PYROSHAPE_DB_USER='pyroshape_app'
```

#### MariaDB or MySQL

Enable PHP's `pdo_mysql` driver. Use an empty development database named `pyroshape_app` and a login with privileges scoped to that database. The SQL below can be run by a local MariaDB administrator. Substitute a development-only password.

```sql
CREATE DATABASE pyroshape_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pyroshape_app'@'127.0.0.1' IDENTIFIED BY 'replace-with-a-local-password';
GRANT ALL PRIVILEGES ON pyroshape_app.* TO 'pyroshape_app'@'127.0.0.1';
```

Apply the existing schema from the app directory, using `mysql` as the client if you run MySQL.

```bash
mariadb --host=127.0.0.1 --user=pyroshape_app --password pyroshape_app \
  < database/migrations/001_auth_schema.sql
export PYROSHAPE_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=pyroshape_app;charset=utf8mb4'
export PYROSHAPE_DB_USER='pyroshape_app'
```

For other PDO engines, supply a compatible schema for `users`, `auth_tokens`, and `email_verification_tokens`, set the matching DSN, and verify the auth flow and application queries on that engine.

#### Development credentials

Enter the database password and generate four independent local auth secrets in the same shell that will start PHP.

```bash
export PYROSHAPE_ENVIRONMENT=development
printf 'Database password: '
read -rs PYROSHAPE_DB_PASSWORD
printf '\n'
export PYROSHAPE_DB_PASSWORD
export PYROSHAPE_JWT_REFRESH_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
export PYROSHAPE_JWT_ACCESS_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
export PYROSHAPE_COOKIE_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
export PYROSHAPE_COOKIE_SALT="$(php -r 'echo bin2hex(random_bytes(32));')"
```

Explicit `development` mode permits HTTP login cookies and includes the email verification token in the registration response. Keep the same keys while testing existing sessions across restarts. For deployments, use `PYROSHAPE_ENVIRONMENT=production`, HTTPS, stable secrets supplied through the process environment, and a runtime database role with only the privileges it needs.

## Frontend

The frontend uses SvelteKit 3, Svelte 5, TypeScript, shadcn-svelte, and Tailwind CSS 4. See [frontend setup](frontend/README.md) for supported Node.js versions.

```bash
cd frontend
npm ci
npm run check
npm run build
```

The build writes static files into `public/app`.

`public/app` is generated output and is ignored by git.

shadcn-svelte component source lives in `frontend/src/lib/components/ui/<component>/`. The uppercase Pyroshape wrappers reuse those components and preserve the starter's control sizes and variants. Configure colors in `frontend/src/app.css` and add components using the CLI configured by `frontend/components.json`:

```bash
cd frontend
npx shadcn-svelte@latest add dialog
```

See [frontend setup](frontend/README.md), the [frontend style guide](docs/frontend-style-guide.md), and the [delivery guide](docs/ai-delivery.md).

### Using another frontend

To serve a static SPA built with another framework, set `settings.frontend.appPath` in `config/config.php` to its build directory. Use `index.html` as the entry point and configure asset URLs under `/app/`, which Pyroshape maps to files in the build directory. Client routes such as `/login` can live at the site root. For a frontend deployed separately, configure its server or reverse proxy to serve its pages and assets and forward `/api/*` and `/health` to PHP under the same origin.

Call the same JSON API and keep cookie-based auth requests same-origin. Start with `/api/session` and the `/api/auth/*` endpoints used by [the starter's session client](frontend/src/lib/session.ts). Register PHP app routes in `src/App.php` via `setupAppRoutes()`. Adapt the frontend and SPA tests to the replacement build and verify an asset, a deep link, an unknown API route, and the login/logout flow.

## Verification

Run the PHP checks after building the default frontend. Auth fixtures require `pdo_sqlite` and run independently of the database you selected for the running app.

```bash
php tests/core_package.php
php tests/auth_flow.php
php tests/auth_service.php
php tests/token_cookie.php
php tests/spa_fallback.php
php tests/smoke.php
php tests/access_logger.php
php tests/frontend_starter.php
php tests/skeleton_identity.php
composer validate
```

## Using the starter for a separate app

Copy the starter into its own project directory. Remove the Pyroshape `path` repository entry from the copy's `composer.json`, then run `composer update tuxweb-it/pyroshape` there to resolve Core from Packagist and update the lockfile. Commit the resulting `composer.lock` and `frontend/package-lock.json` in the new app. Keep the Pyroshape MIT license notice with the copied sources.

Rename the Composer package, PHP namespace and PSR-4 mapping, frontend package and lockfile identity, and the matching assertions in `tests/skeleton_identity.php`. Run the [quick start](#quick-start) and [verification](#verification) before adding your first feature. Keep `AGENTS.md` and `CLAUDE.md` in the copy so Codex and Claude Code can pick up the app conventions.
