<?php

declare(strict_types=1);

use Dam2k\Pyroshape\Core\Auth\UserRepository;

require_once __DIR__ . '/../vendor/autoload.php';

$dsn = getenv('PYROSHAPE_TEST_POSTGRES_DSN');
$username = getenv('PYROSHAPE_TEST_POSTGRES_USER');
$password = getenv('PYROSHAPE_TEST_POSTGRES_PASSWORD');

if (!is_string($dsn) || $dsn === '') {
    echo "postgres user repository skipped (PYROSHAPE_TEST_POSTGRES_DSN not set)\n";
    exit(0);
}

$pdo = new PDO($dsn, is_string($username) ? $username : null, is_string($password) ? $password : null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec(
    'CREATE TEMPORARY TABLE users (
        id BIGSERIAL PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        pwdhash VARCHAR(255) NULL,
        enabled BOOLEAN NOT NULL,
        email_verified BOOLEAN NOT NULL,
        nickname VARCHAR(255) NULL,
        name VARCHAR(255) NULL,
        surname VARCHAR(255) NULL,
        created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
        updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
    )'
);

$repository = new UserRepository($pdo);
$created = $repository->createUnverifiedUser('Postgres@Test.Example', 'password-hash', 'Postgres test');

if (($created['email'] ?? null) !== 'postgres@test.example') {
    fwrite(STDERR, "PostgreSQL registration did not normalize the email\n");
    exit(1);
}

if (($created['enabled'] ?? null) !== false || ($created['email_verified'] ?? null) !== false) {
    fwrite(STDERR, "PostgreSQL registration did not persist false boolean flags\n");
    exit(1);
}

$verified = $repository->markUserVerifiedAndEnabled((int) $created['id']);
if (($verified['enabled'] ?? null) !== true || ($verified['email_verified'] ?? null) !== true) {
    fwrite(STDERR, "PostgreSQL verification did not persist true boolean flags\n");
    exit(1);
}

echo "postgres user repository ok\n";
