<?php

declare(strict_types=1);

putenv('PYROSHAPE_ENVIRONMENT=development');
putenv('PYROSHAPE_DB_DSN=sqlite::memory:');
putenv('PYROSHAPE_DB_USER');
putenv('PYROSHAPE_DB_PASSWORD');

foreach ([
    'PYROSHAPE_JWT_ACCESS_KEY',
    'PYROSHAPE_JWT_REFRESH_KEY',
    'PYROSHAPE_COOKIE_KEY',
    'PYROSHAPE_COOKIE_SALT',
] as $name) {
    putenv($name . '=' . bin2hex(random_bytes(32)));
}
