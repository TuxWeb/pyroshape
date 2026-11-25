# Pyroshape Frontend Style Guide

Use this guide when a user asks for a Pyroshape SPA that should look or feel like a target app. Target app analysis should happen before implementation.

## Target App Analysis

Before implementing, extract the target app's UI system:

- navigation model: top bar, sidebar, tabs, breadcrumbs, user menu
- page density: compact admin UI, spacious SaaS dashboard, mobile-first workflow
- typography: heading scale, body size, table size, label style
- color tokens: background, foreground, primary, accent, muted, destructive, border
- components: buttons, fields, cards, tables, modals, dropdowns, filters, badges
- states: loading, empty, error, validation, disabled, destructive confirmation
- responsive behavior: mobile nav, table overflow, form stacking, action placement

Implement the experience in Pyroshape patterns instead of copying the target app's framework.

## Reusable Primitives

Reusable primitives keep new projects fast and visually consistent.

The component system is shadcn-svelte with Svelte 5 and Tailwind CSS 4. Official component sources live in `frontend/src/lib/components/ui/<component>/`. `frontend/components.json` selects the Nova style and `#lib` aliases. Add new components with `npx shadcn-svelte@latest add <component>` from `frontend/`.

Prefer local Svelte primitives under `frontend/src/lib/components/`:

- `layout/AppShell.svelte`
- `ui/Button.svelte`
- `ui/Card.svelte`
- `ui/EmptyState.svelte`
- `ui/ErrorMessage.svelte`
- `ui/FormField.svelte`
- `ui/Input.svelte`
- `ui/LoadingState.svelte`

The uppercase wrappers use shadcn Button, Input, Card, Label, Spinner, and Alert. Button maps `primary` to `default`, `secondary` to `outline`, `ghost` to `ghost`, and `danger` to `destructive`. Use `onclick` callbacks and `bind:value` for inputs.

Use shadcn components such as Dialog, Dropdown Menu, Tabs, Select, Checkbox, Badge, and Table for additional widgets. Add a Pyroshape wrapper when at least two pages need the same app-specific behavior or visual structure.

## Theme

Keep semantic CSS variables and Tailwind mappings in `frontend/src/app.css`. The theme preserves Pyroshape's teal primary color and supplies card, popover, secondary, muted, destructive, chart, and sidebar tokens. Light values live in `:root`; dark overrides use `.dark`.

Merge component classes with `cn` from `#lib/utils.js`. Prefer semantic utilities such as `bg-card`, `text-muted-foreground`, and `border-border` when adding UI. Preserve the 40px controls defined by the Pyroshape wrappers. There is no UI framework theme attribute on `app.html`.

## Page Composition

- Put global navigation and logout in `AppShell`.
- Keep route components focused on state, domain API calls, and composing UI.
- Put typed API calls in `frontend/src/lib/domain/<domain>.ts`.
- Avoid direct `fetch` calls in route components.
- Use tables for dense operational lists and cards only for framed forms, summaries, and repeated items.
- Do not nest cards inside cards.

## Visual Rules

- Use restrained, multi-token color systems instead of one dominant hue.
- Keep cards at `rounded-md` unless a local design system requires otherwise.
- Use stable dimensions for buttons, tables, toolbars, and fixed-format controls.
- Keep text inside controls short enough to fit on mobile.
- Use loading, empty, and error components consistently.
- Make destructive actions visually distinct and require confirmation when data is deleted.

## Auth Screens

Login, register, verify-email, and dashboard are starter screens. Adapt branding, copy, and layout for the target app, but keep:

- same-origin cookie auth
- `loadSession()` after auth changes
- `ensureSessionLoaded()` for guarded pages
- explicit logout state reset
- backend validation messages through `ApiError.message`
