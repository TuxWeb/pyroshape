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

Prefer local Svelte primitives under `frontend/src/lib/components/`:

- `layout/AppShell.svelte`
- `ui/Button.svelte`
- `ui/Card.svelte`
- `ui/EmptyState.svelte`
- `ui/ErrorMessage.svelte`
- `ui/FormField.svelte`
- `ui/Input.svelte`
- `ui/LoadingState.svelte`

Add new primitives only when at least two pages need the same behavior or visual structure. Good additions for business apps are `Textarea`, `Select`, `Checkbox`, `Modal`, `Dropdown`, `Tabs`, `Badge`, `Table`, and `PageHeader`.

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
