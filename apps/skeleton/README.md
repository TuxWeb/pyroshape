# Pyroshape Skeleton

Starter Slim/PHP-DI application in the Pyroshape monorepo.

## Layout

- `public/index.php` - PHP entrypoint.
- `src/App.php` - app wrapper over `TuxWeb\Pyroshape\Core\App`.
- `config/config.php` - app settings.
- `frontend/` - SvelteKit SPA with shadcn-svelte component source.
- `tests/` - smoke and auth checks.
- `AGENTS.md` - project memory and working conventions for coding agents.
- `SKILL.md` - detailed Pyroshape delivery workflow.

## Install

```bash
composer install
```

Run this command from `apps/skeleton/`. Composer installs `tuxweb/pyroshape` from the monorepo root through the path repository in `composer.json`. The app loads Core through its own `vendor/autoload.php`.

## Runtime

From `apps/skeleton/`, start the PHP development server and open `http://127.0.0.1:8080`.

```bash
php -S 127.0.0.1:8080 -t public
```

`GET /health` works before database and auth setup. The app defaults to production settings, with Secure cookies and user-facing verification tokens disabled. Build the frontend to serve the SPA from `public/app`.

### Local authentication

Apply `database/migrations/001_auth_schema.sql` to an empty development MySQL database. Use PHP with `pdo_mysql` and export the connection settings and four independent auth secrets before starting the server.

```bash
export PYROSHAPE_ENVIRONMENT=development
export PYROSHAPE_DB_DSN='mysql:host=127.0.0.1;dbname=pyroshape_skeleton;charset=utf8mb4'
export PYROSHAPE_DB_USER='pyroshape_skeleton'
read -rs PYROSHAPE_DB_PASSWORD
export PYROSHAPE_DB_PASSWORD
export PYROSHAPE_JWT_REFRESH_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
export PYROSHAPE_JWT_ACCESS_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
export PYROSHAPE_COOKIE_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
export PYROSHAPE_COOKIE_SALT="$(php -r 'echo bin2hex(random_bytes(32));')"
```

Explicit `development` mode permits HTTP login cookies and includes the email verification token in the registration response. For deployments, set `PYROSHAPE_ENVIRONMENT=production`, use HTTPS, and provide stable credentials and keys through the process environment.

## Frontend

The frontend uses SvelteKit 2, Svelte 5, shadcn-svelte, and Tailwind CSS 4. Use Node.js 24 (or a supported version listed in `frontend/package.json`).

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

## Verification

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

Copy the starter and rename its Composer package, app namespace, and frontend package. Replace the `path` repository in the app's `composer.json` with a `vcs` repository at `git@github.com:TuxWeb/pyroshape.git`, and require the Core release you want to install. Run `composer update tuxweb/pyroshape` from the new app to replace the starter lockfile’s local Core reference. See [the Pyroshape repository README](../../README.md) for Composer setup.
