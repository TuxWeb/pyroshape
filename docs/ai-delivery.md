# Pyroshape AI Delivery Guide

Use this guide when Codex builds a new Pyroshape app or extends an existing one.

## Repository Boundaries

- Start new apps from `/home/dino/projects/pyroshape-skeleton`.
- Use `/home/dino/projects/pyroshape-demo` as the reference for completed CRUD features.
- Do not add app domain code to pyroshape-core.
- Change `/home/dino/projects/pyroshape-core` only for reusable backend primitives needed by multiple apps.

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

This vertical slice is the preferred Codex task size. Avoid large rewrites and avoid backend-only or frontend-only work unless the user explicitly asks for that.

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
- Keep operational screens dense, scannable, and responsive.
- Always include loading, empty, error, and disabled/submitting states for data screens.
- Use the session store helpers for auth checks and logout.

## Common Failure Checks

- Login HTTP 500: check database credentials, migrations, and JWT/cookie key length.
- Browser logout not firing: check Svelte button event forwarding.
- SPA route returns 404: rebuild frontend and check `settings.frontend.appPath`.
- API route captured by SPA fallback: register app routes in `setupAppRoutes()` before fallback.
