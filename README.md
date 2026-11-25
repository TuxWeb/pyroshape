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

Run these commands from your app directory.

~~~sh
composer config repositories.pyroshape vcs https://github.com/TuxWeb/pyroshape.git
composer require 'tuxweb/pyroshape:^0.9'
~~~

Commit your app's Composer lockfile to pin the framework revision. The [starter's app class](apps/skeleton/src/App.php) shows how an app extends the framework.

## Test the core library

Run from the repository root with `pdo_sqlite` enabled.

~~~sh
composer install
for test in tests/*.php; do
    php "$test" || exit $?
done
~~~

The [PostgreSQL test](tests/postgres_user_repository.php) also runs when `PYROSHAPE_TEST_POSTGRES_DSN` points to an isolated test database and `pdo_pgsql` is enabled.

## License

Pyroshape is released under the [MIT License](LICENSE).
