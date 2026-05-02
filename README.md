# dam2k/pyroshape-core

Reusable backend toolkit for Pyroshape-style PHP apps.

It provides the lower-level Slim/PHP-DI foundation used by the starter app:

- Slim app bootstrap
- PHP-DI container wiring
- split application logger and HTTP access logger
- JSON error handling
- default middleware
- PDO factory
- JWT auth service, encrypted auth cookies, and auth middleware
- basic auth controller and health controller
- base auth SQL migration

## Local Development

Expected sibling layout:

```text
/home/dino/projects/pyroshape
/home/dino/projects/pyroshape-core
```

In a consuming app `composer.json`, add:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../pyroshape-core",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "dam2k/pyroshape-core": "^0.1"
  }
}
```

Then install:

```bash
php /home/dino/temp/prove/php/composer.phar install
```

Composer symlinks the package into `vendor/dam2k/pyroshape-core`, so edits in `pyroshape-core` apply immediately to the app.

## Minimal App Wrapper

A new app can expose its own namespace while extending the core app:

```php
<?php

declare(strict_types=1);

namespace Acme\MyApp;

class App extends \Dam2k\Pyroshape\Core\App
{
}
```

Front controller:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

(new \Acme\MyApp\App(__DIR__ . '/../config/config.php'))->run();
```

## Required App Config

Create `config/config.php` returning the settings consumed by the core services. At minimum:

```php
<?php

declare(strict_types=1);

return [
    'displayErrorDetails' => false,
    'frontend' => [
        'appPath' => __DIR__ . '/../public/app',
    ],
    'logger' => [
        'app' => [
            'name' => 'app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => 'debug',
        ],
        'access' => [
            'name' => 'access',
            'path' => __DIR__ . '/../logs/access.log',
            'level' => 'info',
        ],
    ],
    'database' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=pyroshape;charset=utf8mb4',
        'username' => 'pyroshape',
        'password' => 'secret',
    ],
    'auth' => [
        'issuer' => 'pyroshape',
        'audience' => 'pyroshape',
        'accessTokenTtl' => 900,
        'refreshTokenTtl' => 2592000,
        'jwtSecret' => 'change-me',
        'cookieSecret' => 'change-me-32-bytes-minimum',
        'cookieName' => 'pyroshape_auth',
    ],
];
```

## Database

Copy or run the bundled migration:

```text
database/migrations/001_auth_schema.sql
```

For a new app, keep app-specific migrations in the app repository. Treat core migrations as base schema examples or vendor migrations copied into the app migration flow.

## Bundle / Release

For local path use, no bundle step is needed. Composer reads this directory directly.

For reusable release:

1. Commit this repository separately.
2. Tag a version, for example `0.1.0`.
3. In app `composer.json`, replace path repository with a VCS repository or private Packagist package.

Example VCS repository:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:dam2k/pyroshape-core.git"
    }
  ],
  "require": {
    "dam2k/pyroshape-core": "^0.1"
  }
}
```

Then update:

```bash
php /home/dino/temp/prove/php/composer.phar update dam2k/pyroshape-core
```

## Boundary

Keep this package framework-level:

- reusable middleware
- reusable auth primitives
- reusable logging/database/container setup
- shared controllers only when generic

Keep product code in each consuming app:

- domain models
- app routes
- app controllers
- app migrations
- app frontend
