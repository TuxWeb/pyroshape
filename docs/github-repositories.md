# Pyroshape GitHub Repository Prep

Pyroshape should be published as three separate repositories.

## Repositories

- `pyroshape-core`: reusable backend toolkit package.
- `pyroshape-skeleton`: clean starter app for new projects.
- `pyroshape-demo`: reference implementation showing a completed SPA vertical slice.

## Publish Order

1. Publish `pyroshape-core`.
2. Publish `pyroshape-skeleton`.
3. Publish `pyroshape-demo`.

The app repositories currently use a local Composer path repository for `dam2k/pyroshape-core`. Keep that during local development. After publishing core, decide whether production apps should consume core through a VCS repository, private Packagist, or a tagged release.

## Before First Push

For each repo:

- Make sure generated directories are ignored: `vendor/`, `frontend/node_modules/`, `frontend/.svelte-kit/`, `public/app/`, `logs/`, and `tmp/`.
- Do not commit local DB credentials or real JWT/cookie secrets.
- Run the repo verification commands from `README.md`.
- Add the GitHub remote only after checking `git status --short`.

## Current Local Note

`pyroshape-demo` is already a valid git repository but has no remote configured.

`pyroshape-core` and `pyroshape-skeleton` currently contain empty `.git/` directories that Git does not recognize as repositories. Before initializing them, remove those empty directories deliberately, then run `git init`. Do this only when ready to create the first local commit.
