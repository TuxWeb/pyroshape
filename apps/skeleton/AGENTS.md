# Project Memory

## Architecture

- `apps/skeleton/` is the starter in the Pyroshape monorepo. Core lives at the repository root and `apps/demo/` is the reference app.
- Backend: PHP/Slim/PHP-DI, Composer dependency `tuxweb-it/pyroshape`, app namespace `TuxWeb\PyroshapeSkeleton`.
- Composer installs Core through the path repository at `../..` in the monorepo and through Packagist in standalone copies. Load dependencies through the app’s `vendor/autoload.php`.
- Frontend requires Node.js 24+ and uses SvelteKit 3, Svelte 5, TypeScript, Tailwind CSS 4, and shadcn-svelte. The official static adapter writes the SPA to `public/app`, served by PHP/Slim.
- PostgreSQL is the default development database. MariaDB and MySQL use the existing `pdo_mysql` migration. Follow the [README](README.md#local-authentication) for DSNs and schema commands.
- Keep ordinary sessions stateless. For forced logout and sensitive operations, follow the [session and authorization guidance](SKILL.md#stateless-sessions-and-forced-logout).

## UI Conventions

- Use shadcn-svelte as the component system for the starter.
- shadcn-svelte component source lives in `frontend/src/lib/components/ui/<component>/`; uppercase Pyroshape components wrap it for app-specific APIs and sizing.
- `frontend/components.json` configures the Nova style, official registry, and `#lib` aliases. Run `npx shadcn-svelte@latest add <component>` from `frontend/` to add components.
- Theme variables and Tailwind mappings live in `frontend/src/app.css`; preserve the teal primary color. Use `cn` from `#lib/utils.js` for class merging.
- Button variants: `primary` → `default`, `secondary` → `outline`, `ghost` → `ghost`, `danger` → `destructive`.
- Use Svelte 5 props, snippets, and event attributes such as `onclick`. Preserve Input `bind:value` and pass numeric constraints as numbers.
- Keep typed API calls in `#lib/api.js` and domain clients. Reuse session helpers for authentication and logout.
- Demo UI patterns may use a different component system; adapt them to this project's shadcn components.

## Verification and Delivery

- For production work, use the app's release procedure and the [Pyroshape deployment guide](https://github.com/TuxWeb/pyroshape/blob/main/docs/operations/deploy-app.md) to verify artifacts, activation, and rollback.
- Run `npm --prefix frontend run check` and `npm --prefix frontend run build` after frontend changes.
- Run PHP tests as separate processes: `for test_file in tests/*.php; do php "$test_file" || exit $?; done`.
- `tests/spa_fallback.php` requires a built `public/app`. `tests/frontend_starter.php` checks the current component setup.
- For UI migrations, verify input bindings, error display, callbacks such as Logout, and desktop/mobile layout in a browser. Simulate API responses when testing UI behavior without changing backend data.
- Do not commit generated `vendor/`, `frontend/node_modules/`, `frontend/.svelte-kit/`, or `public/app/`.
- Do not commit configuration secrets. Commit and push when requested by the user.

See `SKILL.md`, `docs/ai-delivery.md`, `docs/frontend-style-guide.md`, and `frontend/README.md` for detailed guidance.
