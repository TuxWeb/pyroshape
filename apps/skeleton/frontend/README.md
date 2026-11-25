# Pyroshape skeleton frontend

SvelteKit is configured as a static SPA. `npm run build` writes the compiled app to `../public/app`, where PHP/Slim serves it.

This scaffold uses SvelteKit 3, Svelte 5, TypeScript, shadcn-svelte, and Tailwind CSS 4. Use Node.js 22.17 or newer in the 22.x series, or Node.js 24+ (recommended).

Install the locked dependencies with `npm ci`, then run `npm run check` and `npm run build`.

The `bits-ui` override in `package.json` selects Runed 0.38 for its SvelteKit 3 peer support. Remove the override once Bits UI and its dependencies resolve a compatible version themselves.

## UI components

shadcn-svelte distributes component source files rather than a runtime package. The official Button, Input, Card, Label, Spinner, and Alert components are checked in under `src/lib/components/ui/<component>/`. They use Bits UI where needed, Tailwind Variants, Lucide icons, and the `cn` helper in `src/lib/utils.ts`.

The uppercase Pyroshape components in `src/lib/components/ui` wrap these components to preserve the starter's control sizes and app-specific API. `Button` maps `primary`, `secondary`, `ghost`, and `danger` to the shadcn variants; use Svelte 5 `onclick` handlers. `Input` forwards native input attributes and supports `bind:value`. `LoadingState` uses Spinner and `ErrorMessage` uses Alert.

`components.json` configures the official CLI, the Nova style, and the `#lib` aliases. Add further components from `frontend/`:

```bash
npx shadcn-svelte@latest add dialog
```

Theme variables live in `src/app.css`, preserving the Pyroshape teal primary color. Tailwind uses CSS-first configuration and the Vite plugin. Light variables live in `:root`; dark overrides use `.dark`.

## Verification

Run `npm run check` for Svelte/TypeScript diagnostics and `npm run build` to regenerate the static SPA. The root PHP starter check validates the shadcn configuration and required component source files:

```bash
php ../tests/frontend_starter.php
```

When changing wrappers, check input bindings, callback forwarding, validation messages, and responsive layout in a browser. See [project memory](../AGENTS.md) and the [frontend style guide](../docs/frontend-style-guide.md).

Setup reference: [shadcn-svelte's SvelteKit installation guide](https://www.shadcn-svelte.com/docs/installation/sveltekit).
