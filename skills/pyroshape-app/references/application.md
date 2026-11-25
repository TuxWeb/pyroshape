# Application integration

## Start from the skeleton

Use `apps/skeleton/` for a new app. Rename the Composer and frontend package identities, PHP namespaces, and starter identity assertions. The in-repository starter uses a Composer `path` repository pointing to the root via `../..`. For an independent copy, remove the Core path entry and run `composer update tuxweb-it/pyroshape` to resolve Core from Packagist and update the lockfile. Keep the configured dependency source when working on an existing app. Install dependencies with `composer install`.

Follow the app README for runtime settings and the auth migration for the chosen PDO driver. The starter documents PostgreSQL as the default and MariaDB/MySQL as alternatives. Run its smoke and auth checks before introducing domain code. In the starter, `npm --prefix frontend ci`, `npm --prefix frontend run check`, and `npm --prefix frontend run build` produce the static SPA at `public/app`. The `spa_fallback.php` test needs that build. For local smoke tests, follow the app's server setup in its README.

## Extend the application

- The app's `src/App.php` extends `TuxWeb\Pyroshape\Core\App`. Register app routes in `protected function setupAppRoutes(): void` using `$this->getSlimApp()`. Core calls this hook before registering its SPA fallback.
- For a protected route or group, register `->add(RequireAuth::class)->add(CheckValidAuthToken::class)` from `TuxWeb\Pyroshape\Core\Middlewares\Auth`. Slim runs the last registered middleware first. Read the authenticated ID from `$request->getAttribute(CheckValidAuthToken::UID_ATTRIBUTE)` and scope user-owned rows in repository queries. Keep HTTP validation and JSON shaping in controllers and the app's persistence in its domain or repository layer. The standard error envelope is `{"error":{"message":"…"}}`.
- For an end-to-end resource, connect the app migration, repository, controller, route, and the frontend API client and UI as needed by the request. Keep changes within the requested layer for backend-only or frontend-only work. The demo's `ProjectController`, `ProjectRepository`, and `tests/project_api.php` show one implementation of authenticated CRUD.

## Work with the app's frontend

- Inspect the app's `package.json`, frontend configuration, and existing design system. The current starter uses SvelteKit 2, Svelte 5, TypeScript, Tailwind CSS 4, and shadcn-svelte. Its local wrappers forward `onclick` and other native props and preserve `bind:value` on inputs. Keep its token mappings in `frontend/src/app.css` and follow `frontend/components.json` for component generation.
- Reuse the app's typed API client and session store. In the starter, `frontend/src/lib/api.ts` sends same-origin credentials and exposes backend errors via `ApiError`. Use `ensureSessionLoaded()` for protected pages and refresh or clear the store after login or logout. Preserve the app's base-path conventions if it is served under a URL prefix.
- Edit frontend source and regenerate the build. The starter serves it from `public/app` through PHP. When a route, callback, or session flow changes, check it in a browser as well as running the project's relevant checks and build.
- For a replacement SPA, supply `index.html` under `settings.frontend.appPath` and use `/app/` for assets served by PHP. A separately served frontend can proxy the same `/api/*` and `/health` routes under its origin.
