# Pyroshape

Pyroshape is TuxWeb's PHP framework for building web apps with Slim and PHP-DI. The core library handles authentication and sessions, JSON errors, logging, and database access through PDO.

- [Starter](apps/skeleton/README.md) gives you a SvelteKit app to build on.
- [Demo](apps/demo/README.md) shows how to build an authenticated Projects and Tasks app.

## Run the starter

From the repository root, use PHP 8.5+, Composer, and Node.js 24.

~~~sh
cd apps/skeleton
composer install
npm --prefix frontend ci
npm --prefix frontend run build
php -S 127.0.0.1:8080 -t public public/index.php
~~~

Open [localhost:8080](http://127.0.0.1:8080). The [starter guide](apps/skeleton/README.md#local-authentication) covers database setup and sign-in.

## Add Pyroshape to an app

Install [`tuxweb-it/pyroshape` from Packagist](https://packagist.org/packages/tuxweb-it/pyroshape) by running this command from your app directory.

~~~sh
composer require 'tuxweb-it/pyroshape:^0.9.1'
~~~

Commit your app's Composer lockfile to pin the framework revision. The [starter's app class](apps/skeleton/src/App.php) shows how an app extends the framework.

If your app already requires `tuxweb/pyroshape` from the 0.9.0 release, remove that requirement with `composer remove tuxweb/pyroshape --no-update` before running `composer require` above. The PHP namespace remains `TuxWeb\Pyroshape\Core`.

If you previously added the `repositories.pyroshape` GitHub entry, run `composer config --unset repositories.pyroshape` to let Composer resolve the package through Packagist.

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

Push development and documentation changes to `main` as usual. Create a version tag and a GitHub release when a set of changes is ready for a stable release on Packagist.

## License

Pyroshape is released under the [MIT License](LICENSE).
