# Pyroshape skeleton frontend

SvelteKit is configured as a static SPA. `npm run build` writes the compiled app to `../public/app`, where PHP/Slim serves it.

This scaffold uses SvelteKit 2, Svelte 5, Skeleton 5, and Tailwind CSS 4. Use Node.js 20.19+, 22.12+, or 24+ (Node.js 24 is recommended).

Install the locked dependencies with `npm ci`, then run `npm run check` and `npm run build`.

Skeleton's core styles, Svelte component styles, and `mint` theme are imported in `src/app.css`. The active theme is set with `data-theme="mint"` in `src/app.html`. Tailwind uses the CSS-first configuration and the Vite plugin; no JavaScript Tailwind configuration is required.

Local Pyroshape primitives stay in `src/lib/components/ui` for app-specific controls such as `Button`, `Input`, and `Card`, using Skeleton's `btn`, `input`, and `card` classes with the existing Pyroshape color tokens. `LoadingState` uses Skeleton's Svelte `Progress` component. Import other interactive components from `@skeletonlabs/skeleton-svelte` as needed, using local wrappers when useful.

Setup reference: [Skeleton's SvelteKit installation guide](https://www.skeleton.dev/docs/svelte/get-started/installation/sveltekit).
