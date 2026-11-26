---
name: pyroshape-app
description: Use when creating, extending, reviewing, or troubleshooting Pyroshape apps using the Pyroshape starter, Slim/PHP-DI, and a SvelteKit static SPA.
---

# Pyroshape App Skill

Use this for Pyroshape apps with a PHP 8.5+ Slim/PHP-DI backend. The starter uses PostgreSQL and a SvelteKit static SPA served by PHP from `public/app`. Check the app's stack before assuming it uses those defaults.

## Repository Roles

- The monorepo root contains Core, the reusable Slim/PHP-DI package for middleware, auth, logging, PDO, JSON errors, and generic controllers.
- `apps/skeleton/` is the starter for new projects.
- `apps/demo/` shows the auth shell, protected SPA pages, typed API client, CRUD, and tests.

## Delivery Rules

- Inspect the relevant Core, starter, and demo contracts before changing them.
- Keep project domain logic in the app. Put persistence in `src/Domain/<Domain>/`, HTTP validation and response shaping in controllers, and UI data calls in `frontend/src/lib/domain/`.
- Build a vertical slice when a feature needs all layers: migration, repository, controller, route, backend test, TypeScript client, Svelte page, frontend check and build.
- Keep `vendor/`, `node_modules/`, `public/app`, logs, and caches out of Git.
- See `docs/ai-delivery.md` for the delivery workflow and `docs/frontend-style-guide.md` for UI adaptation.

## Starting a New App

Follow the starter README to make a standalone copy, resolve Core from Packagist, and rename the app. Then run its quick start and verification steps before adding domain code. Use the app's selected database schema and PDO driver. Keep the monorepo's Composer path repository when working inside `apps/skeleton/`.

## Backend Pattern

New apps extend core with a thin wrapper:

```php
class App extends \TuxWeb\Pyroshape\Core\App
{
    protected function setupAppRoutes(): void
    {
        $app = $this->getSlimApp();

        $app->group('/api', function ($group): void {
            // app-specific routes here
        });
    }
}
```

For authenticated app routes, use core middleware:

```php
use TuxWeb\Pyroshape\Core\Middlewares\Auth\CheckValidAuthToken;
use TuxWeb\Pyroshape\Core\Middlewares\Auth\RequireAuth;

$app->group('/api', function ($group): void {
    // routes
})->add(RequireAuth::class)->add(CheckValidAuthToken::class);
```

Read authenticated user id from:

```php
$request->getAttribute(CheckValidAuthToken::UID_ATTRIBUTE)
```

Keep app-specific code in the app repo:

- controllers under `src/Controllers/`
- repositories/domain code under `src/Domain/<Domain>/`
- migrations under `database/migrations/`
- tests under `tests/`

Use prepared PDO statements. Scope user-owned domain rows by authenticated user id. Return JSON errors as:

```php
['error' => ['message' => '...']]
```

Backend quality rules:

- Validate body types strictly. Do not silently coerce booleans, integers, or nulls.
- Reject empty PATCH payloads.
- Use `array_key_exists()` when null is a meaningful client value.
- Store timestamps consistently as SQL datetime strings.
- Return JSON envelopes such as `['project' => $project]`, `['projects' => $projects]`, or `['deleted' => true]`.
- Add a focused API test for every new resource.

See `src/Controllers/ProjectController.php`, `src/Domain/Projects/ProjectRepository.php`, and `tests/project_api.php` in `apps/demo/` for a complete CRUD implementation.

## Frontend Pattern

The frontend is a SvelteKit static SPA in `frontend/`, with build output to `public/app`. PHP/Slim serves static SPA assets and falls back to `index.html` for browser routes.

Core files/patterns from the demo:

- `frontend/src/lib/api.ts`: typed JSON `fetch`, same-origin credentials, `ApiError`.
- `frontend/src/lib/session.ts`: session store, `ensureSessionLoaded()`, forced `loadSession()` after auth changes, logout.
- `frontend/src/lib/components/layout/AppShell.svelte`: operational app shell.
- `frontend/src/lib/components/ui/*`: local primitives such as Button/Input/Card/EmptyState/ErrorMessage/FormField/LoadingState.
- `frontend/src/lib/domain/<domain>.ts`: typed API client per domain.
- Routes should stay thin: load state, call domain client, compose UI components.

## shadcn-svelte

Use shadcn-svelte as the project's Svelte 5/Tailwind CSS 4 component system:

- Component source is checked in under `frontend/src/lib/components/ui/<component>/`; shadcn-svelte is a CLI, not a runtime package dependency.
- Keep `frontend/components.json` configured with the Nova style, official registry, CSS path, and `#lib` aliases. Run `npx shadcn-svelte@latest add <component>` from `frontend/` to add components.
- Keep Tailwind Variants, `cn`, Lucide, and `tw-animate-css` installed as required by the checked-in components. Check that dependencies introduced by new components support the app's SvelteKit version.
- Configure semantic CSS variables and Tailwind mappings in `frontend/src/app.css`. Use CSS-first Tailwind configuration with `@tailwindcss/vite`.
- Reuse the uppercase Pyroshape wrappers for app-specific Button variants, Input bindings, Card spacing, FormField labels, LoadingState, and ErrorMessage.
- Preserve Pyroshape structure: typed domain clients, thin route components, PHP/Slim API, with frontend code in each app.
- Share reusable UI primitives through `apps/skeleton/` and keep demo pages in `apps/demo/`.

Frontend quality rules:

- Use typed API functions instead of calling `fetch` directly in route components.
- Reuse the local UI primitives before adding new components.
- Keep operational apps dense and scannable: shell, tables/lists, forms, empty/loading/error states.
- Protect authenticated pages with `ensureSessionLoaded()` and clear anonymous state on logout.
- After login/register/logout, force a fresh session load or explicitly update the session store.
- Guard route detail pages against stale async responses when ids change.
- Use `ApiError.message` for backend validation messages.

Use Svelte 5 callback props such as `<Button onclick={signOut}>`. Forward native attributes and callbacks through wrappers and preserve `$bindable` for wrapped inputs.

## Resource Slice Pattern

When asked to add a new app feature, implement the smallest useful vertical slice:

1. Migration: tables, indexes, foreign keys, `users_id` when records are user-owned.
2. Repository: prepared statements, ownership scoping, typed array payload mapping.
3. Controller: request parsing, strict validation, JSON envelopes, 400/404 behavior.
4. Routes: register inside `setupAppRoutes()`, add auth middleware when needed.
5. Backend test: create user/session, exercise happy path and ownership/error cases.
6. TS domain client: typed request/response functions in `frontend/src/lib/domain/`.
7. Svelte UI: list/create/detail as needed, with loading/empty/error states.
8. Verification: PHP tests, `npm --prefix frontend run check`, `npm --prefix frontend run build`.

For AI agents, this slice is the unit of work. Avoid broad rewrites and avoid building only backend or only frontend unless explicitly requested.

## Auth And Runtime Config

Auth endpoints provided by core:

- `POST /api/auth/register`
- `GET /api/auth/verify-email`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/session`

See the starter README for the `PYROSHAPE_*` environment variables and the PostgreSQL and MariaDB/MySQL schemas. During local auth testing, use separate development keys and explicitly enable development mode for the verification token. If login returns HTTP 500, check the connection settings, migrations, and key lengths. If logout works via HTTP but fails in the browser, inspect the Svelte button event forwarding.

### Stateless sessions and forced logout

Keep ordinary requests stateless. The encrypted login cookie carries a short-lived access JWT and a longer-lived refresh JWT. Core verifies the access JWT for ordinary requests and checks the refresh token's persisted record and current account state when renewing access. Logout clears the browser cookie and revokes its valid refresh token. An issued access JWT remains usable on ordinary routes until its effective expiry.

Design forced logout around that access-token window. For routes that must stop immediately after revocation, check current session or account revocation state, permissions, and resource ownership on the server before acting. Treat the access JWT as identity input only on these routes and require recent reauthentication where warranted. Test that sensitive routes reject a revoked session even while its access JWT is still valid. [Core session contract](https://github.com/TuxWeb/pyroshape/blob/main/skills/pyroshape-app/references/core-contracts.md#stateless-sessions-and-forced-logout)

## When to Change Core

Keep reusable JSON helpers, generic validation, auth and session middleware, logging, database and container behavior, and the shared SPA fallback in Core. Keep app domain tables, migrations, repositories, controllers, frontend components, and local settings in the app.

## Verification Checklist

Core route hook, when working in the monorepo from `apps/skeleton/`:

```bash
php ../../tests/app_routes_before_spa_fallback.php
```

App backend:

```bash
composer validate
php tests/core_package.php
php tests/auth_flow.php
php tests/auth_service.php
php tests/token_cookie.php
php tests/spa_fallback.php
php tests/smoke.php
php tests/access_logger.php
```

If the app has domain APIs, add and run focused tests such as:

```bash
php tests/project_api.php
```

Frontend:

```bash
npm --prefix frontend run check
npm --prefix frontend run build
```

Runtime smoke:

```bash
php -S 127.0.0.1:8080 -t public
curl -sS http://127.0.0.1:8080/health
curl -sS http://127.0.0.1:8080/api/session
```

For auth smoke, register, verify the development token when enabled, login, check `/api/session`, logout, and check `/api/session` again.
