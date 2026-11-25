---
name: pyroshape-app
description: Develop or troubleshoot Pyroshape Core and applications that use the Composer package tuxweb-it/pyroshape. Use for starting an app from apps/skeleton, integrating the Slim/PHP-DI backend, changing shared framework behavior, or working on a Pyroshape app's API, auth, PDO integration, or SvelteKit SPA.
---

# Pyroshape

## Identify the project and the installed package

- In an existing app, start with its `AGENTS.md`, `composer.json`, and application entrypoint. A project may contain the PHP app in a subdirectory. Check the installed Core version with `composer show tuxweb-it/pyroshape` or read `composer.lock` if the package is absent locally. Use Composer's autoloaded source to confirm contracts and settings. A local checkout can differ from the installed package.
- The root package is the reusable PHP library. Keep shared bootstrap, middleware, auth, PDO, logging, and JSON error handling there. Keep domain logic, app routes, app migrations, and frontend source in each app. Trace behavior from the app into the installed Core before deciding where a fix belongs.
- `apps/skeleton/` is the clean starter. `apps/demo/` is the reference for authenticated CRUD. The bundled apps resolve Core from the repository root via Composer. Check the consuming app’s frontend stack and package source before reusing examples.

## Choose the relevant workflow

- For a new app, Composer integration, app routes, or frontend work, read [Application integration](references/application.md).
- For shared Core changes, authentication, database compatibility, or HTTP and SPA routing issues, read [Core contracts](references/core-contracts.md).

## Verify through the affected entrypoint

- Run the app's focused tests and checks. Test the behavior through its real route or Composer integration when those contracts change. Use isolated test databases and local test credentials.
- Core tests are standalone PHP scripts in `tests/` and run in separate PHP processes. Start with `php tests/<script>.php`. For frontend work, use the scripts defined in the app's `package.json` and verify the generated SPA when routing or assets change.
