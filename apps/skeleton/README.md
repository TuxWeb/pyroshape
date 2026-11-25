# Pyroshape Skeleton

Small Slim/PHP-DI skeleton app using the reusable `dam2k/pyroshape-core` toolkit.

## Layout

- `public/index.php` - PHP entrypoint.
- `src/App.php` - app wrapper over `Dam2k\Pyroshape\Core\App`.
- `config/config.php` - app settings.
- `frontend/` - SvelteKit SPA with shadcn-svelte component source.
- `tests/` - smoke and auth checks.
- `AGENTS.md` - project memory and working conventions for coding agents.
- `SKILL.md` - detailed Pyroshape delivery workflow.

## Install

```bash
php /home/dino/temp/prove/php/composer.phar install
```

Composer installs `dam2k/pyroshape-core` through the GitHub VCS repository configured in `composer.json`. Access to that repository is required. A sibling checkout of `pyroshape-core` is optional for developing the reusable toolkit locally.

## Runtime

Configure the app in `config/config.php`, then serve `public/index.php` through PHP-FPM/Apache/Nginx or local PHP server:

```bash
php -S 127.0.0.1:8080 -t public
```

The PHP backend serves API routes and the built static SPA from `public/app`.

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
php /home/dino/temp/prove/php/composer.phar validate
```

## Core Toolkit

Reusable backend code lives in the separate `pyroshape-core` repository and is installed by Composer. The local reference checkout is `/home/dino/projects/pyroshape-core`.

Package name:

```text
dam2k/pyroshape-core
```

Use this app as starter shell. Put reusable Slim/PHP-DI/auth/logging/database behavior in `pyroshape-core`; keep project-specific code in this app under `src/`.
