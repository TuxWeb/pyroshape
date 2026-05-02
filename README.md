# Pyroshape Skeleton

Small Slim/PHP-DI skeleton app using the reusable `dam2k/pyroshape-core` toolkit.

## Layout

- `public/index.php` - PHP entrypoint.
- `src/App.php` - app wrapper over `Dam2k\Pyroshape\Core\App`.
- `config/config.php` - app settings.
- `frontend/` - SvelteKit SPA source.
- `tests/` - smoke and auth checks.
- `/home/dino/projects/pyroshape-core` - reusable toolkit package.

## Install

```bash
php /home/dino/temp/prove/php/composer.phar install
```

The app uses a Composer path repository:

```json
{
  "type": "path",
  "url": "../pyroshape-core",
  "options": {
    "symlink": true
  }
}
```

Keep `pyroshape-skeleton` and `pyroshape-core` as sibling directories during local development.

## Runtime

Configure the app in `config/config.php`, then serve `public/index.php` through PHP-FPM/Apache/Nginx or local PHP server:

```bash
php -S 127.0.0.1:8080 -t public
```

The PHP backend serves API routes and the built static SPA from `public/app`.

## Frontend

```bash
cd frontend
npm install
npm run check
npm run build
```

The build writes static files into `public/app`.

`public/app` is generated output and is ignored by git.

## Verification

```bash
php tests/core_package.php
php tests/auth_flow.php
php tests/auth_service.php
php tests/token_cookie.php
php tests/spa_fallback.php
php tests/smoke.php
php tests/access_logger.php
php /home/dino/temp/prove/php/composer.phar validate
```

## Core Toolkit

Reusable backend code lives in sibling package:

```text
/home/dino/projects/pyroshape-core
```

Package name:

```text
dam2k/pyroshape-core
```

Use this app as starter shell. Put reusable Slim/PHP-DI/auth/logging/database behavior in `pyroshape-core`; keep project-specific code in this app under `src/`.
