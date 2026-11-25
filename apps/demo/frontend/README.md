# Pyroshape demo frontend

SvelteKit is configured as a static SPA. `npm run build` writes the compiled app to `../public/app`, where PHP/Slim serves it.

This scaffold uses Tailwind and local shadcn-svelte-style primitives in `src/lib/components/ui` for `Button`, `Input`, and `Card`. The included `components.json` keeps the project ready for adding real shadcn-svelte components later without changing the app structure.
