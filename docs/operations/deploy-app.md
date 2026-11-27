# Deploy a Pyroshape app

Keep the app's deployment runbook in its own repository and link to it from `AGENTS.md`. Record the actual staging and production targets, the release workflow and preflight command, how the PHP runtime receives configuration, who owns migrations, the smoke tests, and how to roll back. Use the app's established workflow for production changes.

## Build and inspect the release

Build from a clean checkout of the app revision in an isolated staging directory or CI job. Install the versions in the committed `composer.lock` and confirm the installed Core package.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
composer show tuxweb-it/pyroshape
```

Run the platform check in an environment that matches the target. Verify that the web PHP process uses PHP 8.5 or later and loads the extensions the app uses, including OpenSSL for Core's encrypted login cookies and PDO with the driver for the app's DSN. PHP CLI and the web process can load different configurations.

For the bundled SvelteKit SPA, build with Node.js 24 or later and the committed frontend lockfile.

```sh
npm --prefix frontend ci
npm --prefix frontend run check
npm --prefix frontend run build
```

Package the generated `public/app/index.html` and its assets with the PHP app. Node.js is a build dependency for the static SPA. If the app uses a different frontend, verify the build at its configured `settings.frontend.appPath` or through the app's separately served frontend.

### Investigate a rejected dependency path

Before touching production, capture the preflight's exact rejected path. Check the `tuxweb-it/pyroshape` version and reference in `composer.lock` and the actual installation source in `vendor/composer/installed.json`. Inspect the manifest of the staged payload or list the actual archive members with `tar -tf` or `unzip -Z1`. Inspect symlinks as well, especially if the dependency is installed from a local path.

The Packagist-linked `v0.9.2` dist archive includes the example apps. For example, it contains `apps/demo/frontend/src/routes/projects/[id]/+page.svelte` inside the Core package. Starting with `v0.9.3`, dist archives omit the bundled apps, Core tests, and maintenance scripts. A Git clone, Composer source checkout, or local Composer path install can still contain them. `--prefer-dist` requests archive installation where available.

If the manifest confirms that the rejected files are only Core's examples and the app has no dependency on them, exclude the exact `vendor/tuxweb-it/pyroshape/apps/` subtree when assembling the staged artifact. Use the app's existing packaging mechanism and keep the dependency's runtime files, license, and any resources your app uses. Rebuild the artifact, rerun preflight and smoke checks against it, and retain its final manifest and checksum. Use that checked artifact for deployment.

## Prepare the runtime and database

- Set the PHP app's document root to the release's `public/` directory and route app requests through `public/index.php`. Keep configuration, `vendor/`, logs, and writable cache directories outside the document root. Give the PHP process write access where the app expects it, including the Core container cache under `tmp/container/`.
- Serve the app over HTTPS. For the skeleton configuration, set `PYROSHAPE_ENVIRONMENT=production` and supply database credentials, JWT keys, and cookie key and salt through the PHP process environment. Use the same auth secrets across instances and releases, including rollback, to preserve sessions. Keep secret values out of artifacts and deployment output.
- Confirm the target database and review the app's pending migrations and their order. Verify the backup and restore procedure before changing production data. Check whether both the incoming and outgoing app versions can use the resulting schema. If they cannot, settle the rollout order and database recovery procedure before proceeding. An application rollback changes the running code. Schema and data recovery follow the app's migration procedure.
- To roll out TOTP, apply the appropriate additive migration, upgrade Core and the SPA on all instances, then enable enrollment. Before opening it, verify the keyring and browser origin, configure shared IP throttling, and schedule expired-flow cleanup. Use a rollback target that still enforces the factor after users have enrolled. [TOTP migration and rollout for 0.10.0](../releases/v0.10.0.md)

## Promote and verify

Stage the checked artifact through the app's normal release workflow and test it with the production routing and runtime configuration pattern. Keep the previous working release available. Use the app's atomic release switch to promote the staged artifact.

After the switch, check the deployed HTTPS origin and configured base path. Core's `GET /health` confirms that the PHP app bootstraps and returns JSON. Verify database connectivity and other required services through an app-level readiness check. For a typical authenticated SPA, load the entry page, load a static asset and a deep link, and verify login, a database-backed operation, and logout with a designated smoke account.

If the checks fail, use the app's rollback procedure with the migration compatibility or recovery plan. Verify the restored release through the same origin. Record the app revision, Core reference, artifact checksum, preflight and smoke results, migration status, and the release that is serving traffic.
