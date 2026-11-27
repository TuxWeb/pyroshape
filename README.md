# Pyroshape

Build a single-page application with your choice of frontend and a straightforward PHP backend. Pyroshape is an open-source Composer package for PHP 8.5+, built on Slim and PHP-DI and released under the MIT license. The same PHP app can serve your compiled frontend and its JSON API, so the browser can use cookie-based sessions on a single origin.

Pyroshape provides authentication, stateless session handling, logging, JSON errors, and database access through PDO. Extend the app class to add your own routes, middleware, and business logic. Start with the bundled SvelteKit app or connect an existing frontend to the API.

This PHP Backend comes from years of hand developing and testing by [TuxWeb S.r.l.](https://www.tuxweb.it/), and we already use this in production for most of our products and services. · [Support the maintainer in Monero](DONATE.md)

## What you can build quickly

- **An internal tool** for tracking requests, assets, or inventory. Use the starter's sign-in flow and add a list, an edit form, and a PHP API for your records.
- **A customer portal** for projects, tasks, or service requests. Follow the demo's pattern for showing signed-in users their own records.
- **A dashboard for an existing system.** Keep your frontend and add PHP endpoints that read from your database or call the services you already use.

The [starter](apps/skeleton/README.md) gives you registration, email verification, and login. The [Projects and Tasks demo](apps/demo/README.md) shows a complete database-backed feature you can use as a reference when you build your first one.

## Share your feedback

We'd love to hear what you're building with Pyroshape, what made your work easier, and where you got stuck. Positive and critical feedback are both welcome, whether you've shipped an app or only tried the starter. Tell us what worked, what felt unclear or missing, and what would make Pyroshape more useful to you.

You can [share your experience on GitHub](https://github.com/TuxWeb/pyroshape/issues/new) or write directly to Pyroshape's author, [Dino Ciuffetti (dino@tuxweb.it)](mailto:dino@tuxweb.it). We'd be happy to hear from you by email, too.

## Start a new app

Use PHP 8.5+, Composer, and Node.js 24+. The starter includes a Svelte 5, SvelteKit 3, and TypeScript frontend with shadcn-svelte components. PostgreSQL is recommended for new apps, and MariaDB is also supported.

Clone the repository with `git clone https://github.com/TuxWeb/pyroshape.git` if needed. The Git checkout contains the starter and demo. From the repository root, copy the tracked starter files and MIT license to a new sibling directory.

~~~sh
mkdir ../my-app &&
git archive HEAD:apps/skeleton | tar -xf - -C ../my-app &&
git show HEAD:LICENSE > ../my-app/LICENSE &&
cd ../my-app
~~~

Remove the local `path` repository entry from `composer.json` so Composer can resolve Core from Packagist. Rename the Composer package, application PHP namespace, and npm package for your project. The [standalone setup guide](apps/skeleton/README.md#using-the-starter-for-a-separate-app) lists the files to update.

~~~sh
composer update tuxweb-it/pyroshape
npm --prefix frontend ci
npm --prefix frontend run check
npm --prefix frontend run build
~~~

To register and sign in on first launch, apply the database migration and export the connection settings and development auth keys in the same shell. Follow the [local authentication guide](apps/skeleton/README.md#local-authentication).

~~~sh
php -S 127.0.0.1:8080 -t public public/index.php
~~~

Open [the local app](http://127.0.0.1:8080). For a quick smoke check, the app's [`/health`](http://127.0.0.1:8080/health) endpoint also works before database setup.

You can use another frontend with the same `/api` endpoints. For a same-origin static SPA, build an `index.html` and assets that Core can serve from `public/app`. See [using another frontend](apps/skeleton/README.md#using-another-frontend).

### Try the starter in this repository

The bundled starter uses the Core checkout through a Composer `path` repository. From the repository root, run the following commands.

~~~sh
cd apps/skeleton
composer install
npm --prefix frontend ci
npm --prefix frontend run build
php -S 127.0.0.1:8080 -t public public/index.php
~~~

## Add Pyroshape to an app

Install [`tuxweb-it/pyroshape` from Packagist](https://packagist.org/packages/tuxweb-it/pyroshape) by running this command from your app directory.

~~~sh
composer require 'tuxweb-it/pyroshape:^0.10.0'
~~~

Commit your app's Composer lockfile to pin the framework revision. The [starter's app class](apps/skeleton/src/App.php) shows how an app extends the framework.

If your app already requires `tuxweb/pyroshape` from the 0.9.0 release, remove that requirement with `composer remove tuxweb/pyroshape --no-update` before running `composer require` above. The PHP namespace remains `TuxWeb\Pyroshape\Core`.

If you previously added the `repositories.pyroshape` GitHub entry, run `composer config --unset repositories.pyroshape` to let Composer resolve the package through Packagist.

## Deploy an app

Build the application from its lockfiles, validate the exact artifact, and check the app through its public URL after activation. The [production deployment guide](docs/operations/deploy-app.md) covers preflight failures, runtime settings, smoke checks, and rollback for developers and coding agents.

## Work with Codex or Claude Code

Open the project directory in your coding agent and start with its `AGENTS.md`. A standalone app includes the [starter instructions](apps/skeleton/AGENTS.md) and [delivery workflow](apps/skeleton/SKILL.md). When working in this repository, use the [repository instructions](AGENTS.md) and [Pyroshape app skill](skills/pyroshape-app/SKILL.md). The [demo](apps/demo/README.md) shows authenticated CRUD for projects and tasks.

For a new app, ask the agent to run the starter's [verification checks](apps/skeleton/README.md#verification) before adding domain routes, migrations, or UI.

Both the [framework checkout](CLAUDE.md) and the [starter](apps/skeleton/CLAUDE.md) provide a `CLAUDE.md` that imports the corresponding `AGENTS.md` for Claude Code. See [Claude Code's instructions for importing files](https://code.claude.com/docs/en/memory#import-additional-files).

## Test the core library

Run from the root of a Git checkout with `pdo_sqlite` enabled.

~~~sh
composer install
for test in tests/*.php; do
    php "$test" || exit $?
done
~~~

The [PostgreSQL test](tests/postgres_user_repository.php) also runs when `PYROSHAPE_TEST_POSTGRES_DSN` points to an isolated test database and `pdo_pgsql` is enabled.

The [TOTP HTTP test](tests/auth_totp.php) uses a disposable SQLite database by default. To verify the real migrations and HTTP flow, set `AUTH_TOTP_TEST_DRIVER` to `pgsql` or `mysql` and provide `AUTH_TOTP_TEST_DSN`, `AUTH_TOTP_TEST_USER`, and `AUTH_TOTP_TEST_PASSWORD` for an empty test database. The [refresh concurrency test](tests/auth_totp_concurrency.php) uses `AUTH_TOTP_RACE_DSN`, `AUTH_TOTP_RACE_USER`, and `AUTH_TOTP_RACE_PASSWORD` with an empty database and the PHP `pcntl` extension. Each script creates its own schema in the supplied database.

## Maintain PHP file headers

Stage new PHP files first, then run `php scripts/add-php-headers.php` from a Git checkout to add the project signature and MIT notice to tracked PHP files. The script excludes `vendor/` at every depth and keeps existing signatures. Use `php scripts/add-php-headers.php --check` to list files that still need a header.

## Releases

**0.10.0** adds optional TOTP sign-in, enrollment and recovery, and coordinated refresh-session revocation. Apply the additive auth migration before enabling TOTP. The release notes list the migration for each database, the affected tables, and the steps to deploy and verify the feature. [Pyroshape 0.10.0 release notes](docs/releases/v0.10.0.md)

Push development and documentation changes to `main` as usual. The package maintainer decides when to publish a stable release on Packagist and sets its version. Create the version tag and GitHub release only with the maintainer's explicit authorization.

Before publishing, inspect the release commit's `git archive` output. Composer dist archives include Core, its database migrations, and its registered Composer binaries. Use a Git checkout to access the bundled apps and Core tests.

## License

Pyroshape is released under the [MIT License](LICENSE).
