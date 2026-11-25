# Pyroshape

Pyroshape is TuxWeb's PHP framework. The Composer library, starter, and reference app are developed in this repository.

| Directory | Purpose |
| --- | --- |
| `src/` | The `tuxweb/pyroshape` library with Slim/PHP-DI bootstrapping, authentication, middleware, JSON error handling, logging, and PDO integration |
| `apps/skeleton/` | [Starter app](apps/skeleton/README.md) for a new Pyroshape project |
| `apps/demo/` | [Reference app](apps/demo/README.md) with authentication and a Projects and Tasks API |
| `skills/pyroshape-app/` | [Project skill](skills/pyroshape-app/SKILL.md) for working on Pyroshape and its apps |

## Develop Pyroshape

Core requires PHP 8.5 or later and Composer. Both app frontends were verified on Node.js 24. The starter lists its supported Node.js versions in its frontend manifest. Run the Core tests with the dependencies installed at the repository root.

~~~sh
composer install
for test_file in tests/*.php; do
    php "$test_file" || exit $?
done
~~~

The authentication tests use SQLite and require `pdo_sqlite`. The PostgreSQL repository test requires `pdo_pgsql` and an isolated test database configured with `PYROSHAPE_TEST_POSTGRES_DSN`. Set `PYROSHAPE_TEST_POSTGRES_USER` and `PYROSHAPE_TEST_POSTGRES_PASSWORD` as needed.

Each app has its own Composer and npm dependencies. Install dependencies inside the app you want to run and follow its README for configuration and database setup. The two apps resolve the Core package from the root of this checkout through their Composer `path` repositories.

## Use the library in another app

Projects with access to the GitHub repository can resolve the development branch through Composer. In the consuming app's `composer.json`, define the VCS repository and require the package.

~~~json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:TuxWeb/pyroshape.git"
        }
    ],
    "require": {
        "tuxweb/pyroshape": "dev-main"
    }
}
~~~

Run `composer update tuxweb/pyroshape` to resolve the dependency. Commit the app's lockfile to pin the commit it uses. When using the starter as a separate project, apply this VCS configuration in place of the in-repository `path` entry and resolve a new app lockfile. Use the starter's `src/App.php`, `public/index.php`, and configuration as working integration examples.

## Application boundaries

Keep reusable framework behavior in Core. Register an application's routes through `setupAppRoutes()` and keep domain logic, frontend source, and app-specific migrations in that app. The bundled `database/migrations/001_auth_schema.sql` provides the base MySQL authentication schema.
