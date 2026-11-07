# Project Memory

## Architecture

- `pyroshape-skeleton` is a clean starter, separate from the `pyroshape-core` toolkit and `pyroshape-demo` reference app.
- Backend: PHP/Slim/PHP-DI, Composer dependency `dam2k/pyroshape-core`, app namespace `Dam2k\PyroshapeSkeleton`.
- Composer uses the GitHub VCS repository in `composer.json`; tests must resolve the installed core through Composer rather than assume a sibling checkout.
- Frontend: SvelteKit 2, Svelte 5, Tailwind CSS 4, shadcn-svelte. Static SPA output goes to `public/app`, served by PHP/Slim.

## UI Conventions

- Skeleton UI has been replaced completely by shadcn-svelte. Keep its packages, imports, classes, and `data-theme` attribute out of the frontend.
- Official shadcn sources live in `frontend/src/lib/components/ui/<component>/`; uppercase Pyroshape components wrap them for app-specific APIs and sizing.
- `frontend/components.json` configures the Nova style, official registry, and `$lib` aliases. Run `npx shadcn-svelte@latest add <component>` from `frontend/` to add components.
- Theme variables and Tailwind mappings live in `frontend/src/app.css`; preserve the teal primary color. Use `cn` from `$lib/utils` for class merging.
- Button variants: `primary` → `default`, `secondary` → `outline`, `ghost` → `ghost`, `danger` → `destructive`.
- Use Svelte 5 `onclick` callbacks on Button wrappers. Preserve Input `bind:value` and pass numeric constraints as numbers.
- Keep typed API calls in `$lib/api` and domain clients. Reuse session helpers for authentication and logout.
- Sibling demo UI patterns may use a different component system; adapt them to this project's shadcn components.

## Verification and Delivery

- Run `npm --prefix frontend run check` and `npm --prefix frontend run build` after frontend changes.
- Run PHP tests as separate processes: `for test_file in tests/*.php; do php "$test_file" || exit $?; done`.
- `tests/spa_fallback.php` requires a built `public/app`. `tests/frontend_starter.php` checks the current component setup.
- For UI migrations, verify input bindings, error display, callbacks such as Logout, and desktop/mobile layout in a browser. Simulate API responses when testing UI behavior without changing backend data.
- Do not commit generated `vendor/`, `frontend/node_modules/`, `frontend/.svelte-kit/`, or `public/app/`.
- Do not commit configuration secrets. Commit and push when requested by the user.

See `SKILL.md`, `docs/ai-delivery.md`, `docs/frontend-style-guide.md`, and `frontend/README.md` for detailed guidance.
