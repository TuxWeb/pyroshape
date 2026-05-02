# Pyroshape Demo SPA Design

Date: 2026-05-02
Status: Draft for review

## Purpose

`pyroshape-demo` will be the first complete example of a Pyroshape-driven single page application. It should demonstrate how a real consuming app uses `dam2k/pyroshape-core` for backend primitives while keeping product-specific domain code and frontend code in the app repository.

The demo will use a small Projects + Tasks domain because it proves authenticated CRUD, protected navigation, list/detail views, forms, API error handling, loading states, empty states, and reusable frontend structure without becoming product-specific.

## Repository Boundary

`pyroshape-core` remains a backend toolkit. It should keep reusable Slim/PHP-DI setup, auth primitives, middleware, logging, database utilities, JSON error handling, and generic controllers.

`pyroshape-demo` will be a consuming application. It will contain the app wrapper, app config, app-specific migrations, app-specific controllers/repositories, API routes, tests, and the SvelteKit frontend.

The existing `pyroshape-skeleton` remains the minimal starter. `pyroshape-demo` becomes the richer reference implementation.

## Architecture

The application will follow the current skeleton integration model:

- PHP/Slim front controller in `public/index.php` boots an app class that extends `Dam2k\Pyroshape\Core\App`.
- The PHP backend serves API routes under `/api/*` and health checks under `/health`.
- The built SvelteKit SPA is emitted to `public/app`.
- Pyroshape's SPA fallback serves `public/app/index.html` for browser routes that are not API or health routes.
- SvelteKit runs as a static SPA with `ssr = false` and `adapter-static` fallback.

The frontend and backend communicate through same-origin `fetch` calls with `credentials: 'same-origin'`, matching Pyroshape's cookie-based auth flow.

## Backend Domain Surface

The demo domain will expose authenticated project and task endpoints:

- `GET /api/projects` lists the current user's projects.
- `POST /api/projects` creates a project.
- `GET /api/projects/{id}` returns one project with its tasks.
- `PATCH /api/projects/{id}` updates project metadata.
- `DELETE /api/projects/{id}` deletes a project and its tasks.
- `POST /api/projects/{id}/tasks` creates a task in a project.
- `PATCH /api/tasks/{id}` updates task title, status, or ordering fields.
- `DELETE /api/tasks/{id}` deletes a task.

All domain routes require an authenticated session. Data is scoped to the authenticated user, so a user cannot read or mutate another user's projects or tasks.

The backend should return consistent JSON payloads and use Pyroshape's existing JSON error conventions. Validation failures should return clear field-level or message-level errors with appropriate HTTP statuses.

## Frontend Routes

The SPA will include these first routes:

- `/` redirects to `/dashboard` when authenticated and `/login` otherwise.
- `/login` supports login and redirects to the dashboard on success.
- `/register` supports account creation and development email verification links when enabled.
- `/verify-email` completes email verification from a token query parameter.
- `/dashboard` is protected and summarizes active projects and open tasks.
- `/projects` is protected and lists projects with create/edit/delete actions.
- `/projects/[id]` is protected and shows a project detail view with task creation, completion, editing, and deletion.

Protected pages should handle three states explicitly: checking session, unauthenticated, and authenticated.

## Frontend Structure

The frontend will keep the skeleton's SvelteKit + Tailwind setup and strengthen it into a reusable app shell:

- `src/lib/api.ts` provides typed JSON fetch helpers, `ApiError`, and shared error parsing.
- `src/lib/session.ts` owns session state, session loading, login/logout helpers, and session refresh.
- `src/lib/domain/projects.ts` owns typed project/task API calls.
- `src/lib/components/ui/*` contains local UI primitives such as Button, Input, Card, Dialog, EmptyState, ErrorMessage, LoadingState, and FormField.
- `src/lib/components/layout/*` contains AppShell, TopNav, SideNav or responsive navigation, and protected-page wrappers.
- Route components stay thin: load state, call API modules, compose UI components.

This structure should be easy to copy into future Pyroshape apps without moving frontend code into `pyroshape-core`.

## UX Direction

The UI should feel like a practical backend-driven web app, not a marketing page. The first screen after login is an operational dashboard. Layout should be dense enough for repeated use, with restrained colors, clear navigation, strong form states, and readable tables/lists.

Expected states must be designed, not left as raw text:

- initial loading
- empty project list
- empty task list
- validation error
- API/server error
- unauthenticated protected page
- successful create/update/delete feedback

## Data Flow

On app mount, the layout loads `/api/session` and stores the result in the session store. Authenticated routes use that store to decide whether to render, redirect, or show an unauthenticated state.

Domain pages call the project API module. Mutations should initially use reload-after-save for correctness and simplicity. Optimistic UI can be added later only where it improves the experience without hiding backend failures.

## Testing

Backend verification should cover:

- core package wiring still works through Composer path repository
- auth flow still works
- SPA fallback still serves built assets and does not capture `/api/*` or `/health/*`
- project/task CRUD requires auth
- project/task data is scoped per user
- validation and not-found cases return JSON errors

Frontend verification should cover:

- `npm run check`
- `npm run build`
- at least one smoke path against the PHP server after build: load app, register/login, create a project, create a task, complete it, log out

## Out of Scope For The First Version

The first version will not include teams, sharing, roles/permissions beyond user ownership, realtime updates, offline mode, SSR, file uploads, billing, or a published reusable frontend package.

## Decisions For Version 1

Use Projects + Tasks as the demo domain.

Use the existing SvelteKit static SPA model from `pyroshape-skeleton`.

Keep frontend code in `pyroshape-demo`, not in `pyroshape-core`.
