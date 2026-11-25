# Pyroshape demo frontend

SvelteKit builds a static SPA. Use Node.js 22.17 or a later release in the 22.x line, or Node.js 24 or later. Run `npm ci`, `npm run check`, and `npm run build` from this directory. The build writes to `../public/app`, where the PHP backend serves the app and its assets.

The interface uses Tailwind CSS and local Svelte components in `src/lib/components/ui`. See the [demo README](../README.md) for the PHP server, database setup, and runtime environment.
