<?php

/*
 * Copyright (c) 2026 TuxWeb
 * Author: Dino Ciuffetti <dino@tuxweb.it>, TuxWeb S.r.l. - https://www.tuxweb.it/
 * Date: 2026-11-27
 * SPDX-License-Identifier: MIT
 *
 * Permission is granted to use, modify, and distribute this software under
 * the MIT License. Keep the copyright and license notices with copies or
 * substantial portions. This software is provided as is, without warranty.
 * See LICENSE for the full terms.
 */

declare(strict_types=1);

use DI\Container;
use Firebase\JWT\JWT;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use TuxWeb\Pyroshape\Core\Auth\AuthService;
use TuxWeb\Pyroshape\Core\Auth\UserRepository;

require __DIR__ . '/../vendor/autoload.php';

$dsn = getenv('AUTH_TOTP_RACE_DSN');
if (!$dsn || !function_exists('pcntl_fork')) {
    echo "TOTP refresh race skipped (set AUTH_TOTP_RACE_DSN for an empty database, with pcntl)\n";
    exit(0);
}

function racePdo(): PDO
{
    return new PDO(
        (string) getenv('AUTH_TOTP_RACE_DSN'),
        getenv('AUTH_TOTP_RACE_USER') ?: null,
        getenv('AUTH_TOTP_RACE_PASSWORD') ?: null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function raceExpect(bool $result, string $message): void
{
    if (!$result) {
        throw new RuntimeException($message);
    }
}

$pdo = racePdo();
$location = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? '/postgresql' : '';
foreach (['001_auth_schema.sql', '002_auth_totp.sql'] as $migration) {
    $pdo->exec(file_get_contents(__DIR__ . "/../database/migrations$location/$migration"));
}

$now = gmdate('Y-m-d H:i:s');
$email = 'totp-race@example.test';
$password = 'TOTP Test Password Only';
$pdo->prepare(
    'INSERT INTO users (email, pwdhash, enabled, email_verified, created_at, updated_at) VALUES (?, ?, TRUE, TRUE, ?, ?)'
)->execute([$email, password_hash($password, PASSWORD_BCRYPT), $now, $now]);
$userId = (int) $pdo->query('SELECT id FROM users')->fetchColumn();
$container = new Container();
$container->set('settings', [
    'jwt_refresh_token' => ['key' => random_bytes(32), 'algorithm' => 'HS256', 'ttl' => 120, 'auto_refresh' => 240],
    'jwt_access_token' => ['key' => random_bytes(32), 'algorithm' => 'HS256', 'ttl' => 1],
    'login_cookie' => [
        'name' => 'pyroshape_race',
        'key' => bin2hex(random_bytes(32)),
        'salt' => bin2hex(random_bytes(16)),
        'path' => '/',
        'secure' => false,
    ],
]);
$responses = new ResponseFactory();
$auth = new AuthService($container, new UserRepository($pdo));
$login = $auth->login($email, $password, $responses->createResponse());
raceExpect($login['authenticated'] === true, 'The legacy login must issue a session before enrollment.');
$cookie = explode(';', $login['response']->getHeaderLine('Set-Cookie'), 2)[0];
[, $cookieValue] = explode('=', $cookie, 2);
$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/session')
    ->withCookieParams(['pyroshape_race' => rawurldecode($cookieValue)]);
unset($auth, $pdo);

$streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
raceExpect($streams !== false, 'Could not open process signal sockets.');
[$parentStream, $childStream] = $streams;
$pid = pcntl_fork();
raceExpect($pid !== -1, 'Could not fork refresh process.');

if ($pid === 0) {
    fclose($parentStream);
    stream_set_timeout($childStream, 8);
    try {
        raceExpect(fgets($childStream) === "go\n", 'The enrollment process did not take the lock.');
        $repository = new class(racePdo(), $childStream) extends UserRepository {
            public function __construct(PDO $pdo, private mixed $signal)
            {
                parent::__construct($pdo);
            }

            public function withLockedUser(int $userId, callable $action): mixed
            {
                fwrite($this->signal, "locking\n");

                return parent::withLockedUser($userId, $action);
            }
        };
        JWT::$timestamp = time() + 3;
        $refresh = (new AuthService($container, $repository))->getSession($request, $responses->createResponse());
        raceExpect(!$refresh['response']->hasHeader('Set-Cookie'), 'A late rejection must not remove a newer cookie.');
        fwrite($childStream, ($refresh['authenticated'] ? 'authenticated' : 'rejected') . "\n");
        fclose($childStream);
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        fclose($childStream);
        exit(1);
    }
}

fclose($childStream);
stream_set_timeout($parentStream, 8);
try {
    $pdo = racePdo();
    $repository = new UserRepository($pdo);
    $repository->withLockedUser($userId, function () use ($repository, $userId, $parentStream): void {
        fwrite($parentStream, "go\n");
        raceExpect(fgets($parentStream) === "locking\n", 'Refresh did not reach the shared user lock.');
        $read = [$parentStream];
        $write = null;
        $except = null;
        raceExpect(stream_select($read, $write, $except, 0, 200_000) === 0, 'Refresh did not wait for enrollment.');

        $now = time();
        $repository->totpRepository()->activate(
            $userId,
            'synthetic-ciphertext-for-concurrency-test',
            hash('sha256', random_bytes(16)),
            intdiv($now, 30),
            gmdate('Y-m-d H:i:s', $now)
        );
    });
    raceExpect(fgets($parentStream) === "rejected\n", 'Refresh accepted a token revoked during enrollment.');
    raceExpect((int) $pdo->query('SELECT COUNT(*) FROM auth_tokens WHERE revocation_time IS NULL')->fetchColumn() === 0,
        'Concurrent refresh left a live successor token.');
    pcntl_waitpid($pid, $childStatus);
    raceExpect(pcntl_wifexited($childStatus) && pcntl_wexitstatus($childStatus) === 0, 'Refresh process failed.');
} finally {
    fclose($parentStream);
    pcntl_waitpid($pid, $remainingStatus);
}

echo "TOTP enrollment vs refresh race passed\n";
