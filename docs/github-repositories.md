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

This starter consumes `dam2k/pyroshape-core` through the GitHub VCS repository configured in `composer.json`. A Composer path repository is an optional local override for developing core, rather than a requirement for using the starter.

## Before Push

For each repo:

- Make sure generated directories are ignored: `vendor/`, `frontend/node_modules/`, `frontend/.svelte-kit/`, `public/app/`, `logs/`, and `tmp/`.
- Do not commit local DB credentials or real JWT/cookie secrets.
- Run the repo verification commands from `README.md`.
- Inspect `git status --short` and the staged diff before committing.
- Include `frontend/components.json`, shadcn component sources, and the npm lockfile when changing the frontend component system.
- Keep README, delivery/style guides, `AGENTS.md`, and `SKILL.md` aligned with architectural changes.

## This Repository

`pyroshape-skeleton` is an initialized Git repository. Its configured origin is `git@github.com:dam2k/pyroshape-skeleton.git` and its main branch is `main`. Use the existing repository and remote; do not reinitialize Git. Verify sibling repository remotes independently before publishing changes there.
