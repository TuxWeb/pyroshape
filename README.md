# Pyroshape

Build a single-page application with your choice of frontend and a straightforward PHP backend. Pyroshape is TuxWeb's open-source Composer package for PHP 8.5+, built on Slim and PHP-DI and released under the MIT license. The same PHP app can serve your compiled frontend and its JSON API, so the browser can use cookie-based sessions on a single origin.

Pyroshape provides authentication, session handling, logging, JSON errors, and database access through PDO. Extend the app class to add your own routes, middleware, and business logic. Start with the bundled SvelteKit app or connect an existing frontend to the API.

## Start a new app

Use PHP 8.5+, Composer, and Node.js 24. The starter includes a Svelte 5, SvelteKit 3, and TypeScript frontend with shadcn-svelte components. PostgreSQL is recommended for new apps, and MariaDB is also supported.

Clone the repository with `git clone https://github.com/TuxWeb/pyroshape.git` if needed. From the repository root, copy the tracked starter files and MIT license to a new sibling directory.

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
composer require 'tuxweb-it/pyroshape:^0.9.1'
~~~

Commit your app's Composer lockfile to pin the framework revision. The [starter's app class](apps/skeleton/src/App.php) shows how an app extends the framework.

If your app already requires `tuxweb/pyroshape` from the 0.9.0 release, remove that requirement with `composer remove tuxweb/pyroshape --no-update` before running `composer require` above. The PHP namespace remains `TuxWeb\Pyroshape\Core`.

If you previously added the `repositories.pyroshape` GitHub entry, run `composer config --unset repositories.pyroshape` to let Composer resolve the package through Packagist.

## Work with Codex or Claude Code

Open the project directory in your coding agent and start with its `AGENTS.md`. A standalone app includes the [starter instructions](apps/skeleton/AGENTS.md) and [delivery workflow](apps/skeleton/SKILL.md). When working in this repository, use the [repository instructions](AGENTS.md) and [Pyroshape app skill](skills/pyroshape-app/SKILL.md). The [demo](apps/demo/README.md) shows authenticated CRUD for projects and tasks.

For a new app, ask the agent to run the starter's [verification checks](apps/skeleton/README.md#verification) before adding domain routes, migrations, or UI.

Both the [framework checkout](CLAUDE.md) and the [starter](apps/skeleton/CLAUDE.md) provide a `CLAUDE.md` that imports the corresponding `AGENTS.md` for Claude Code. See [Claude Code's instructions for importing files](https://code.claude.com/docs/en/memory#import-additional-files).

## Test the core library

Run from the repository root with `pdo_sqlite` enabled.

~~~sh
composer install
for test in tests/*.php; do
    php "$test" || exit $?
done
~~~

The [PostgreSQL test](tests/postgres_user_repository.php) also runs when `PYROSHAPE_TEST_POSTGRES_DSN` points to an isolated test database and `pdo_pgsql` is enabled.

## Releases

Push development and documentation changes to `main` as usual. The package maintainer decides when to publish a stable release on Packagist and sets its version. Create the version tag and GitHub release only with the maintainer's explicit authorization.

## License

Pyroshape is released under the [MIT License](LICENSE).
