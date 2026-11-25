# Application integration

## Start from the skeleton

Use `apps/skeleton/` for a new app. Rename the Composer and frontend package identities, PHP namespaces, and starter identity assertions. The in-repository starter uses a Composer `path` repository pointing to the root via `../..`. For an independent copy, remove the Core path entry and run `composer update tuxweb-it/pyroshape` to resolve Core from Packagist and update the lockfile. Keep the configured dependency source when working on an existing app. Install dependencies with `composer install`.

Follow the app README for runtime settings used by `config/config.php` and apply the app's auth schema and migrations to a test database. Install and build the frontend using the starter's lockfile and supported Node version. Run its smoke and auth checks before introducing domain code. In the current starter, `npm --prefix frontend ci`, `npm --prefix frontend run check`, and `npm --prefix frontend run build` produce the static SPA at `public/app`. The `spa_fallback.php` test needs that build. For local smoke tests, follow the app's server setup, such as `php -S 127.0.0.1:8080 -t public` in the starter.

## Extend the application

- The app's `src/App.php` extends `TuxWeb\Pyroshape\Core\App`. Register app routes in `protected function setupAppRoutes(): void` using `$this->getSlimApp()`. Core calls this hook before registering its SPA fallback.
- For a protected route or group, register `->add(RequireAuth::class)->add(CheckValidAuthToken::class)` from `TuxWeb\Pyroshape\Core\Middlewares\Auth`. Slim runs the last registered middleware first. Read the authenticated ID from `$request->getAttribute(CheckValidAuthToken::UID_ATTRIBUTE)` and scope user-owned rows in repository queries. Keep HTTP validation and JSON shaping in controllers and the app's persistence in its domain or repository layer. The standard error envelope is `{"error":{"message":"…"}}`.
- For an end-to-end resource, connect the app migration, repository, controller, route, and the frontend API client and UI as needed by the request. Keep changes within the requested layer for backend-only or frontend-only work. The demo's `ProjectController`, `ProjectRepository`, and `tests/project_api.php` show one implementation of authenticated CRUD.

## Work with the app's frontend

- Inspect the app's `package.json`, Svelte configuration, and existing design system. Legacy apps may use different versions or components. The current starter uses Svelte 5, Tailwind CSS 4, and shadcn-svelte. Its local wrappers forward `onclick` and other native props and preserve `bind:value` on inputs. Keep its token mappings in `frontend/src/app.css` and follow `frontend/components.json` for component generation.
- Reuse the app's typed API client and session store. In the starter, `frontend/src/lib/api.ts` sends same-origin credentials and exposes backend errors via `ApiError`. Use `ensureSessionLoaded()` for protected pages and refresh or clear the store after login or logout. Preserve the app's base-path conventions if it is served under a URL prefix.
- Edit frontend source and regenerate the build. The starter serves it from `public/app` through PHP. When a route, callback, or session flow changes, check it in a browser as well as running the project's relevant checks and build.
