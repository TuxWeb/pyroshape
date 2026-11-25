# Pyroshape AI Delivery Guide

Use this guide when a developer or coding agent builds a new Pyroshape app or extends an existing one. Start with the [README quick start](../README.md#quick-start), then keep the app's chosen database and frontend stack in [AGENTS.md](../AGENTS.md).

## Repository Boundaries

- Start new apps from `apps/skeleton/`.
- Use `apps/demo/` as the reference for completed CRUD features.
- Keep app domain code in the app.
- Change Core at the monorepo root for reusable backend primitives needed by multiple apps.

## Delivery Unit

Build features as a vertical slice:

1. SQL migration in `database/migrations/`.
2. Repository in `src/Domain/<Domain>/`.
3. Controller in `src/Controllers/`.
4. Routes in `src/App.php` through `setupAppRoutes()`.
5. Focused PHP API test in `tests/`.
6. Typed frontend client in `frontend/src/lib/domain/`.
7. Svelte route pages with loading, empty, error, and success states.
8. Verification with PHP tests, `npm --prefix frontend run check`, and `npm --prefix frontend run build`.

Use a slice of this size for features that need both layers. Keep backend-only and frontend-only requests within their requested scope.

## Backend Expectations

- Use prepared PDO statements.
- Scope user-owned records by authenticated user id.
- Validate request body types strictly.
- Reject empty PATCH payloads.
- Return JSON envelopes such as `{"items":[...]}`, `{"item":{...}}`, or `{"deleted":true}`.
- Return JSON errors as `{"error":{"message":"..."}}`.

## Frontend Expectations

- Use `frontend/src/lib/api.ts` for JSON requests.
- Put typed domain API functions in `frontend/src/lib/domain/`.
- Reuse local Svelte primitives before adding new components.
- Use the checked-in shadcn-svelte components in `frontend/src/lib/components/ui/<component>/` and the uppercase Pyroshape wrappers for app-specific controls.
- Add components from `frontend/` using `npx shadcn-svelte@latest add <component>`. Keep `components.json`, component sources, dependencies, and `package-lock.json` in sync.
- Configure semantic theme variables in `frontend/src/app.css`; use `cn` from `$lib/utils` to merge classes.
- Use Svelte 5 `onclick` callbacks for Button wrappers and preserve `$bindable` for Input values. Pass numeric input constraints as numbers, for example `minlength={8}`.
- Keep operational screens dense, scannable, and responsive.
- Always include loading, empty, error, and disabled/submitting states for data screens.
- Use the session store helpers for auth checks and logout.

## Common Failure Checks

- Login HTTP 500: check database credentials, migrations, and JWT/cookie key length.
- Browser logout not firing: check that `onclick` is forwarded through the Pyroshape Button wrapper to the shadcn Button.
- SPA route returns 404: rebuild frontend and check `settings.frontend.appPath`.
- API route captured by SPA fallback: register app routes in `setupAppRoutes()` before fallback.

## Project Memory

Keep `AGENTS.md` aligned with the current architecture and working conventions. Keep the detailed workflow in `SKILL.md` and the UI setup in `frontend/README.md`. When changing the component system, update all of these together with the starter checks in `tests/frontend_starter.php`.
