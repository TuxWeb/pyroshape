---
name: pyroshape-app
description: Use when creating, extending, reviewing, or troubleshooting Pyroshape apps using pyroshape-skeleton, pyroshape-core, Slim/PHP-DI, and a SvelteKit static SPA.
---

# Pyroshape App Skill

Use this for Pyroshape apps: PHP 8.5 + Slim/PHP-DI backend, `dam2k/pyroshape-core`, and a SvelteKit static SPA served by PHP from `public/app`.

## Repository Roles

- `/home/dino/projects/pyroshape-core`: reusable backend library only. Keep it framework-level: Slim/PHP-DI bootstrap, middleware, auth primitives, logging, PDO, JSON errors, generic controllers. Do not add product/domain/frontend code here.
- `/home/dino/projects/pyroshape-skeleton`: clean starter for new apps. Start new real projects from this.
- `/home/dino/projects/pyroshape-demo`: reference implementation. Read/copy patterns from it for auth shell, protected SPA pages, typed API client, CRUD, and tests. Do not clone it unless the new app should include its Projects + Tasks demo domain.

## Codex Delivery Rules

- Before changing code, inspect `pyroshape-core`, `pyroshape-skeleton`, and `pyroshape-demo` patterns relevant to the task.
- Keep project-specific domain logic in the app repo. Change `pyroshape-core` only for reusable primitives that at least two apps would need.
- Prefer copying proven demo patterns into the new app before inventing new architecture.
- Build a vertical slice end to end: migration, repository, controller, route, backend test, TS client, Svelte page, frontend check/build.
- Keep routes thin. Put persistence in `src/Domain/<Domain>/`, HTTP validation/response shaping in controllers, and UI data calls in `frontend/src/lib/domain/`.
- Preserve generated output boundaries: `vendor/`, `node_modules/`, `public/app`, logs, and caches are not source.
- Read `docs/ai-delivery.md` for Codex delivery workflow, `docs/frontend-style-guide.md` for UI adaptation, and `docs/github-repositories.md` before publishing repos.

## Starting A New App

1. Copy `pyroshape-skeleton` to the new project directory.
2. Rename Composer package, PHP namespace, app wrapper, README, and tests from skeleton names to the new app name.
3. Keep `dam2k/pyroshape-core` as a Composer path repository while developing locally.
   - For sibling layout: `../pyroshape-core`.
   - If working inside a nested git worktree, adjust the relative path accordingly and fix it before merging back.
4. Run Composer through `/home/dino/temp/prove/php/composer.phar install`.
5. Build frontend from `frontend/` with `npm install`, `npm run check`, `npm run build`.
6. Serve with `php -S 127.0.0.1:8080 -t public`.

After creating the app, immediately verify the empty starter before adding domain code. This separates scaffold problems from feature problems.

## Backend Pattern

New apps extend core with a thin wrapper:

```php
class App extends \Dam2k\Pyroshape\Core\App
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
use Dam2k\Pyroshape\Core\Middlewares\Auth\CheckValidAuthToken;
use Dam2k\Pyroshape\Core\Middlewares\Auth\RequireAuth;

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

Use `pyroshape-demo/src/Controllers/ProjectController.php`, `src/Domain/Projects/ProjectRepository.php`, and `tests/project_api.php` as the canonical CRUD pattern.

## Frontend Pattern

The frontend is a SvelteKit static SPA in `frontend/`, with build output to `public/app`. PHP/Slim serves static SPA assets and falls back to `index.html` for browser routes.

Core files/patterns from the demo:

- `frontend/src/lib/api.ts`: typed JSON `fetch`, same-origin credentials, `ApiError`.
- `frontend/src/lib/session.ts`: session store, `ensureSessionLoaded()`, forced `loadSession()` after auth changes, logout.
- `frontend/src/lib/components/layout/AppShell.svelte`: operational app shell.
- `frontend/src/lib/components/ui/*`: local primitives such as Button/Input/Card/EmptyState/ErrorMessage/FormField/LoadingState.
- `frontend/src/lib/domain/<domain>.ts`: typed API client per domain.
- Routes should stay thin: load state, call domain client, compose UI components.

## Skeleton UI

Use Skeleton 5 as the project's Svelte 5/Tailwind CSS 4 component library. The frontend follows the official SvelteKit setup:

- Keep `@skeletonlabs/skeleton` and `@skeletonlabs/skeleton-svelte` installed as frontend dev dependencies.
- Import Tailwind CSS, both Skeleton packages, and `@skeletonlabs/skeleton/themes/mint` in `frontend/src/app.css`; set `data-theme="mint"` in `frontend/src/app.html`.
- Use CSS-first Tailwind configuration with `@tailwindcss/vite`.
- Use Skeleton's `btn`, `input`, and `card` classes in local primitives. Import interactive components such as Progress, Dialog, Menu, Tabs, and Toast from `@skeletonlabs/skeleton-svelte`.
- Keep app pages insulated through local wrappers under `frontend/src/lib/components/` when that reduces coupling or preserves Pyroshape-specific styling.
- Preserve Pyroshape structure: typed domain clients, thin route components, PHP/Slim API, and no frontend code in `pyroshape-core`.
- Promote only generic reusable primitives back to `pyroshape-skeleton`; keep demo/domain pages in `pyroshape-demo`.

Frontend quality rules:

- Use typed API functions instead of calling `fetch` directly in route components.
- Reuse the local UI primitives before adding new components.
- Keep operational apps dense and scannable: shell, tables/lists, forms, empty/loading/error states.
- Protect authenticated pages with `ensureSessionLoaded()` and clear anonymous state on logout.
- After login/register/logout, force a fresh session load or explicitly update the session store.
- Guard route detail pages against stale async responses when ids change.
- Use `ApiError.message` for backend validation messages.

Important Svelte detail: if wrapping a native button in a component, forward click events explicitly:

```svelte
<button on:click {...$$restProps}>
  <slot />
</button>
```

Without this, parent `<Button on:click={...}>` handlers can fail.

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

Local runtime requires valid DB credentials and long JWT/cookie secrets. Placeholder `change-me-*` keys are not enough for current JWT signing and can cause `Provided key is too short` errors.

Before manual browser testing:

1. Set `config/config.php` database DSN/user/password.
2. Apply auth migration and app migrations.
3. Use long development secrets for `jwt_refresh_token.key`, `jwt_access_token.key`, `login_cookie.key`, and `login_cookie.salt`.
4. Rebuild frontend after Svelte changes.

If login returns HTTP 500, first check DB credentials/migrations and JWT/cookie key length. If logout does nothing in the browser but works through curl, inspect the Svelte button event forwarding.

## When To Change Core

Acceptable `pyroshape-core` changes:

- generic JSON response/request helpers
- generic validation helpers
- auth/session middleware improvements
- reusable logging/database/container behavior
- SPA fallback behavior shared by all apps

Not acceptable in `pyroshape-core`:

- app domain tables, repositories, controllers, or migrations
- Svelte components or frontend assets
- demo-specific routes or project/task logic
- local machine config or secrets

## Verification Checklist

Core route hook:

```bash
cd /home/dino/projects/pyroshape-core
php tests/app_routes_before_spa_fallback.php
```

App backend:

```bash
php /home/dino/temp/prove/php/composer.phar validate
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
