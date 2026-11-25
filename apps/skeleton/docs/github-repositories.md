# Pyroshape Repository

The [TuxWeb/pyroshape repository](https://github.com/TuxWeb/pyroshape) contains the reusable Composer package at the root, the starter in `apps/skeleton/`, and the reference app in `apps/demo/`.

The starter installs `tuxweb/pyroshape` from the repository root through its Composer path repository. For a starter copied into its own repository, configure Composer with the [GitHub VCS installation instructions](../../../README.md).

## Before publishing

- Run the verification commands in [the starter README](../README.md).
- Include frontend source, component configuration, and lockfiles for the changes under review.
- Keep `vendor/`, `frontend/node_modules/`, `frontend/.svelte-kit/`, `public/app/`, logs, and temporary output out of Git.
- Provide local credentials and auth secrets through the process environment.
