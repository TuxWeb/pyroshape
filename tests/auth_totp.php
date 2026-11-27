<?php

/*
 * Copyright (c) 2026 TuxWeb
 * Author: Dino Ciuffetti <dino@tuxweb.it>, TuxWeb S.r.l. - https://www.tuxweb.it/
 * Date: 2026-11-27
 * SPDX-License-Identifier: MIT
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 */

declare(strict_types=1);

use Monolog\Logger;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use TuxWeb\Pyroshape\Core\App;

require __DIR__ . '/../vendor/autoload.php';

final class AuthTotpTestClock implements ClockInterface
{
    public static int $timestamp = 1_800_000_000;

    public function __construct()
    {
    }

    public function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp(self::$timestamp);
    }
}

function totpHttpRequest(string $method, string $path, ?array $payload = null, ?string $cookie = null): ServerRequestInterface
{
    $request = (new ServerRequestFactory())->createServerRequest($method, 'https://auth.example.test' . $path)
        ->withHeader('Host', 'auth.example.test')
        ->withHeader('Origin', 'https://auth.example.test')
        ->withHeader('Accept', 'application/json');

    if ($payload !== null) {
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    if ($cookie !== null && $cookie !== '') {
        $request = $request->withHeader('Cookie', $cookie)->withCookieParams(totpHttpParseCookies($cookie));
    }

    return $request;
}

function totpHttpParseCookies(string $header): array
{
    $cookies = [];
    foreach (explode(';', $header) as $part) {
        $part = trim($part);
        if ($part !== '' && str_contains($part, '=')) {
            [$name, $value] = explode('=', $part, 2);
            $cookies[$name] = urldecode($value);
        }
    }

    return $cookies;
}

function totpHttpJson(ResponseInterface $response): array
{
    $decoded = json_decode((string) $response->getBody(), true);
    if (!is_array($decoded)) {
        totpHttpFail('Expected JSON response, got: ' . (string) $response->getBody());
    }

    return $decoded;
}

function totpHttpAssert(int $expected, ResponseInterface $response, string $label): array
{
    $body = totpHttpJson($response);
    if ($response->getStatusCode() !== $expected) {
        totpHttpFail("$label: expected HTTP $expected, got {$response->getStatusCode()}: " . json_encode($body));
    }

    return $body;
}

function totpHttpFail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function totpHttpCookie(ResponseInterface $response, string $name): string
{
    foreach ($response->getHeader('Set-Cookie') as $header) {
        $pair = explode(';', $header, 2)[0];
        if (str_starts_with($pair, $name . '=')) {
            return $pair;
        }
    }

    totpHttpFail("Missing $name cookie");
}

function totpHttpCode(string $secret, int $timestamp): string
{
    return TOTP::create($secret)->at($timestamp);
}

function totpHttpWrongCode(string $correct): string
{
    return str_pad((string) (((int) $correct + 1) % 1000000), 6, '0', STR_PAD_LEFT);
}

function totpHttpCreateSchema(PDO $pdo, string $driver): void
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($driver === 'sqlite') {
        $pdo->exec(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, pwdhash TEXT NULL,
    enabled INTEGER NOT NULL DEFAULT 1, email_verified INTEGER NOT NULL DEFAULT 0,
    nickname TEXT NULL, name TEXT NULL, surname TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    totp_enabled INTEGER NOT NULL DEFAULT 0, totp_secret_encrypted TEXT NULL,
    totp_enabled_at TEXT NULL, totp_disabled_at TEXT NULL, totp_last_used_step INTEGER NULL,
    totp_recovery_code_hash TEXT NULL, auth_version INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE auth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT, users_id INTEGER NOT NULL, jti TEXT NOT NULL UNIQUE,
    token_hash TEXT NOT NULL, released TEXT NOT NULL, expiration TEXT NOT NULL, revocation_time TEXT NULL
);
CREATE TABLE email_verification_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT, users_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, expiration TEXT NOT NULL, used_at TEXT NULL
);
CREATE TABLE auth_totp_flows (
    flow_id TEXT PRIMARY KEY, users_id INTEGER NOT NULL, purpose TEXT NOT NULL, auth_version INTEGER NOT NULL,
    expires_at TEXT NOT NULL, consumed_at TEXT NULL, failed_attempts INTEGER NOT NULL DEFAULT 0,
    credential_hash TEXT NULL UNIQUE, refresh_jti TEXT NULL, remember_me INTEGER NULL, candidate_encrypted TEXT NULL
);
CREATE INDEX auth_totp_flows_expiration_index ON auth_totp_flows (expires_at, flow_id);
CREATE INDEX auth_totp_flows_users_id_index ON auth_totp_flows (users_id, consumed_at, purpose, expires_at);
CREATE TABLE auth_totp_throttle (
    users_id INTEGER PRIMARY KEY, window_started_at TEXT NOT NULL, failed_attempts INTEGER NOT NULL
);
SQL);
        return;
    }

    if (!in_array($driver, ['pgsql', 'mysql'], true)) {
        totpHttpFail('AUTH_TOTP_TEST_DRIVER must be sqlite, pgsql, or mysql');
    }

    $root = dirname(__DIR__);
    $migrationDirectory = $driver === 'pgsql'
        ? "$root/database/migrations/postgresql"
        : "$root/database/migrations";
    foreach (['001_auth_schema.sql', '002_auth_totp.sql'] as $migration) {
        $sql = file_get_contents("$migrationDirectory/$migration");
        if ($sql === false) {
            totpHttpFail("Could not read migration $migration");
        }
        $pdo->exec($sql);
    }
}

$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}
$testId = bin2hex(random_bytes(6));
// For native-engine runs, point these variables at a newly created empty disposable database.
// The script applies the checked-in migrations and leaves an externally supplied database intact.
$driver = getenv('AUTH_TOTP_TEST_DRIVER') ?: 'sqlite';
$dsn = getenv('AUTH_TOTP_TEST_DSN') ?: 'sqlite:' . $tmpDir . '/test-auth-totp-' . $testId . '.sqlite';
$dbUser = getenv('AUTH_TOTP_TEST_USER') ?: null;
$dbPassword = getenv('AUTH_TOTP_TEST_PASSWORD') ?: null;
$sqliteFile = $driver === 'sqlite' && getenv('AUTH_TOTP_TEST_DSN') === false
    ? substr($dsn, strlen('sqlite:'))
    : null;
$configFile = "$tmpDir/test-auth-totp-$testId-config.php";
$logPath = "$tmpDir/test-auth-totp-$testId";
$loginCookieName = 'pyroshape_totp_login';
$challengeCookieName = 'pyroshape_totp_challenge';
$basePath = '/portal';
$publicOrigin = 'https://auth.example.test';
AuthTotpTestClock::$timestamp = 1_800_000_000;
$config = [
    'settings' => [
        'displayErrorDetails' => false,
        'environment' => 'development',
        'basePath' => $basePath,
        'logger' => ['name' => 'PyroshapeTotpTest', 'path' => "$logPath-app.log", 'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s', 'logFormat' => "%datetime% %message%\n", 'timezone' => 'UTC'],
        'accessLogger' => ['name' => 'PyroshapeTotpTest', 'path' => "$logPath-access.log", 'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s', 'logFormat' => "%datetime% %message%\n", 'timezone' => 'UTC'],
        'headers' => [],
        'database' => ['driver' => $driver, 'dsn' => $dsn, 'username' => $dbUser, 'password' => $dbPassword],
        'jwt_refresh_token' => ['secret' => hash('sha256', 'auth-totp-refresh-secret'), 'ttl' => 86400,
            'auto_refresh' => 0, 'algorithm' => 'HS256'],
        'jwt_access_token' => ['secret' => hash('sha256', 'auth-totp-access-secret'), 'ttl' => 3600,
            'algorithm' => 'HS256'],
        'login_cookie' => ['name' => $loginCookieName, 'ttl' => 86400, 'path' => $basePath,
            'secure' => false, 'httpOnly' => true, 'sameSite' => 'Lax',
            'key' => hash('sha256', 'auth-totp-cookie-key'), 'salt' => 'auth-totp-cookie-salt'],
        'emailVerification' => ['devExposeToken' => true],
        'auth' => ['totp' => [
            'allowEnrollment' => true,
            'publicOrigin' => $publicOrigin,
            'challengeCookieName' => $challengeCookieName,
            'challengeCookiePath' => $basePath . '/api/auth',
            'keys' => ['test-v1' => base64_encode(hash('sha256', 'auth-totp-encryption-key', true))],
            'activeKeyId' => 'test-v1',
            'issuer' => 'Pyroshape Test',
        ]],
    ],
];
$configSource = "<?php\n\$testConfig = " . var_export($config, true)
    . ";\n\$testConfig['" . ClockInterface::class . "'] = new \\" . AuthTotpTestClock::class
    . "();\nreturn \$testConfig;\n";
file_put_contents($configFile, $configSource);

$pdo = new PDO($dsn, $dbUser, $dbPassword);
totpHttpCreateSchema($pdo, $driver);
register_shutdown_function(static function () use ($tmpDir, $testId, $sqliteFile): void {
    foreach (glob("$tmpDir/test-auth-totp-$testId-*") ?: [] as $file) {
        @unlink($file);
    }
    if ($sqliteFile !== null) {
        @unlink($sqliteFile);
    }
});

$app = new App($configFile);
$slim = $app->getSlimApp();
$email = "totp-$testId@example.test";
$password = 'CorrectHorseBatteryStaple!42';
$request = static fn (string $method, string $path, ?array $body = null, ?string $cookie = null): ServerRequestInterface =>
    totpHttpRequest($method, $basePath . $path, $body, $cookie);

$response = $slim->handle($request('POST', '/api/auth/register', ['email' => $email, 'password' => $password]));
$registered = totpHttpAssert(201, $response, 'register');
$verify = $slim->handle($request('GET', '/api/auth/verify-email?token=' . rawurlencode($registered['verificationToken'])));
totpHttpAssert(200, $verify, 'verify email');

$login = $slim->handle($request('POST', '/api/auth/login', [
    'email' => $email, 'password' => $password, 'rememberMe' => false,
]));
totpHttpAssert(200, $login, 'initial password login');
$loginCookie = totpHttpCookie($login, $loginCookieName);

$status = $slim->handle($request('GET', '/api/auth/totp', null, $loginCookie));
$statusBody = totpHttpAssert(200, $status, 'initial TOTP status');
if (($statusBody['enabled'] ?? true) !== false || ($statusBody['enrollmentAvailable'] ?? false) !== true) {
    totpHttpFail('Expected an unenrolled account with enrollment available: ' . json_encode($statusBody));
}

$wrongOrigin = $request('POST', '/api/auth/totp/setup', ['currentPassword' => $password], $loginCookie)
    ->withHeader('Origin', 'https://attacker.example.test');
$originRejected = $slim->handle($wrongOrigin);
$originBody = totpHttpAssert(403, $originRejected, 'wrong-origin setup request');
if (($originBody['error']['code'] ?? null) !== 'origin_rejected') {
    totpHttpFail('Wrong browser origin did not return origin_rejected');
}

$setup = $slim->handle($request('POST', '/api/auth/totp/setup', ['currentPassword' => $password], $loginCookie));
$setupBody = totpHttpAssert(200, $setup, 'start setup');
if ($setup->getHeaderLine('Cache-Control') !== 'no-store') {
    totpHttpFail('Provisioning response must be marked no-store');
}
$setupCode = totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp);
$confirm = $slim->handle($request('POST', '/api/auth/totp/setup/confirm', [
    'flowId' => $setupBody['flowId'], 'code' => $setupCode,
], $loginCookie));
$enrolled = totpHttpAssert(200, $confirm, 'confirm setup');
if (($enrolled['reauthenticationRequired'] ?? false) !== true || empty($enrolled['recoveryCode'])) {
    totpHttpFail('Setup did not issue recovery material and require reauthentication');
}
$recoveryCode = $enrolled['recoveryCode'];

// Advancing the DI clock separates enrollment's consumed code from sign-in.
AuthTotpTestClock::$timestamp += 30;
$closedConfigFile = "$tmpDir/test-auth-totp-$testId-closed-config.php";
file_put_contents($closedConfigFile, '<?php $config = require ' . var_export($configFile, true)
    . '; $config["settings"]["auth"]["totp"]["allowEnrollment"] = false; return $config;');
$closedEnrollment = (new App($closedConfigFile))->getSlimApp();
$loginRequest = $request('POST', '/api/auth/login', [
    'email' => $email, 'password' => $password, 'rememberMe' => true,
]);
$loginStep = $closedEnrollment->handle($loginRequest->withUri(
    $loginRequest->getUri()->withScheme('http')->withHost('backend.example.test')
));
$challenge = totpHttpAssert(202, $loginStep, 'password step for enrolled account behind a proxy with enrollment closed');
if (($challenge['authenticated'] ?? true) !== false || ($challenge['next'] ?? null) !== 'totp_required') {
    totpHttpFail('Enrolled password step must remain unauthenticated and request TOTP');
}
foreach ($loginStep->getHeader('Set-Cookie') as $header) {
    if (str_starts_with($header, $loginCookieName . '=')) {
        totpHttpFail('Password step issued a login cookie before TOTP proof');
    }
}
$challengeCookie = totpHttpCookie($loginStep, $challengeCookieName);
$challengeHeaders = strtolower($loginStep->getHeaderLine('Set-Cookie'));
foreach (['path=/portal/api/auth', 'httponly', 'secure', 'samesite=strict'] as $attribute) {
    if (!str_contains($challengeHeaders, $attribute)) {
        totpHttpFail("Challenge cookie is missing $attribute");
    }
}
$wrong = totpHttpWrongCode(totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp));
$wrongResponse = $slim->handle($request('POST', '/api/auth/login/totp', [
    'flowId' => $challenge['flowId'], 'code' => $wrong,
], $challengeCookie));
$wrongBody = totpHttpAssert(401, $wrongResponse, 'wrong login code');
if (($wrongBody['error']['code'] ?? null) !== 'invalid_code') {
    totpHttpFail('Wrong TOTP code did not return invalid_code');
}
$success = $slim->handle($request('POST', '/api/auth/login/totp', [
    'flowId' => $challenge['flowId'], 'code' => totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp),
], $challengeCookie));
$authenticated = totpHttpAssert(200, $success, 'complete TOTP login');
if (($authenticated['authenticated'] ?? false) !== true) {
    totpHttpFail('TOTP completion did not authenticate');
}
$sessionCookie = totpHttpCookie($success, $loginCookieName);
$tokenCount = (int) $pdo->query('SELECT COUNT(*) FROM auth_tokens WHERE revocation_time IS NULL')->fetchColumn();
if ($tokenCount !== 1) {
    totpHttpFail("Expected exactly one refresh session after TOTP login, found $tokenCount");
}
$replay = $slim->handle($request('POST', '/api/auth/login/totp', [
    'flowId' => $challenge['flowId'], 'code' => totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp),
], $challengeCookie));
$replayBody = totpHttpAssert(409, $replay, 'replayed login challenge');
if (($replayBody['error']['code'] ?? null) !== 'flow_unavailable'
    || (int) $pdo->query('SELECT COUNT(*) FROM auth_tokens WHERE revocation_time IS NULL')->fetchColumn() !== 1
) {
    totpHttpFail('A replayed challenge was not rejected without issuing a session');
}

// Fail the refresh-token write after TOTP verification. The transaction must leave the proof reusable.
if ($driver === 'sqlite') {
    AuthTotpTestClock::$timestamp += 30;
    $rollbackStart = $slim->handle($request('POST', '/api/auth/login', [
        'email' => $email, 'password' => $password,
    ]));
    $rollbackChallenge = totpHttpAssert(202, $rollbackStart, 'start rollback challenge');
    $rollbackCookie = totpHttpCookie($rollbackStart, $challengeCookieName);
    $rollbackCode = totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp);
    $pdo->exec("CREATE TRIGGER auth_totp_test_fail_session BEFORE INSERT ON auth_tokens
        BEGIN SELECT RAISE(ABORT, 'injected session write failure'); END");
    $rollbackResponse = $slim->handle($request('POST', '/api/auth/login/totp', [
        'flowId' => $rollbackChallenge['flowId'], 'code' => $rollbackCode,
    ], $rollbackCookie));
    $rollbackBody = totpHttpAssert(503, $rollbackResponse, 'failed session issuance after proof');
    if (($rollbackBody['error']['code'] ?? null) !== 'auth_unavailable'
        || (int) $pdo->query('SELECT COUNT(*) FROM auth_tokens WHERE revocation_time IS NULL')->fetchColumn() !== 1
    ) {
        totpHttpFail('Failed session issuance did not roll back without creating a refresh session');
    }
    $pdo->exec('DROP TRIGGER auth_totp_test_fail_session');
    $rollbackRetry = $slim->handle($request('POST', '/api/auth/login/totp', [
        'flowId' => $rollbackChallenge['flowId'], 'code' => $rollbackCode,
    ], $rollbackCookie));
    totpHttpAssert(200, $rollbackRetry, 'retry same proof after transaction rollback');
}

// A challenge expires according to the injected clock, without waiting on wall time.
AuthTotpTestClock::$timestamp += 30;
$expiredStart = $slim->handle($request('POST', '/api/auth/login', [
    'email' => $email, 'password' => $password,
]));
$expiredChallenge = totpHttpAssert(202, $expiredStart, 'start expiring login');
$expiredCookie = totpHttpCookie($expiredStart, $challengeCookieName);
AuthTotpTestClock::$timestamp += 301;
$expiredResponse = $slim->handle($request('POST', '/api/auth/login/totp', [
    'flowId' => $expiredChallenge['flowId'], 'code' => totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp),
], $expiredCookie));
$expiredBody = totpHttpAssert(409, $expiredResponse, 'expired login challenge');
if (($expiredBody['error']['code'] ?? null) !== 'flow_unavailable') {
    totpHttpFail('Expired login challenge did not return flow_unavailable');
}

// Expire the failed-flow window for the next independent lifecycle proof.
AuthTotpTestClock::$timestamp += 901;

// A flow closes after five failures; the account budget continues across fresh flows.
$flowThrottleStart = $slim->handle($request('POST', '/api/auth/login', [
    'email' => $email, 'password' => $password,
]));
$flowThrottle = totpHttpAssert(202, $flowThrottleStart, 'start per-flow throttle');
$flowThrottleCookie = totpHttpCookie($flowThrottleStart, $challengeCookieName);
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $correct = totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp);
    $failedResponse = $slim->handle($request('POST', '/api/auth/login/totp', [
        'flowId' => $flowThrottle['flowId'], 'code' => totpHttpWrongCode($correct),
    ], $flowThrottleCookie));
    totpHttpAssert(401, $failedResponse, "same-flow failure $attempt");
}
$closedFlow = $slim->handle($request('POST', '/api/auth/login/totp', [
    'flowId' => $flowThrottle['flowId'], 'code' => totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp),
], $flowThrottleCookie));
$closedFlowBody = totpHttpAssert(409, $closedFlow, 'flow closed after five failures');
if (($closedFlowBody['error']['code'] ?? null) !== 'flow_unavailable') {
    totpHttpFail('A flow with five failed proofs remained usable');
}

for ($attempt = 6; $attempt <= 10; $attempt++) {
    $failedStart = $slim->handle($request('POST', '/api/auth/login', [
        'email' => $email, 'password' => $password,
    ]));
    $failedChallenge = totpHttpAssert(202, $failedStart, "throttle flow $attempt");
    $failedCookie = totpHttpCookie($failedStart, $challengeCookieName);
    $correct = totpHttpCode($setupBody['manualKey'], AuthTotpTestClock::$timestamp);
    $failedResponse = $slim->handle($request('POST', '/api/auth/login/totp', [
        'flowId' => $failedChallenge['flowId'], 'code' => totpHttpWrongCode($correct),
    ], $failedCookie));
    totpHttpAssert(401, $failedResponse, "failed factor proof $attempt");
    AuthTotpTestClock::$timestamp += 1;
}
$throttled = $slim->handle($request('POST', '/api/auth/login', [
    'email' => $email, 'password' => $password,
]));
$throttledBody = totpHttpAssert(429, $throttled, 'account-wide TOTP throttle');
if (($throttledBody['error']['code'] ?? null) !== 'rate_limited'
    || (int) $throttled->getHeaderLine('Retry-After') < 1
) {
    totpHttpFail('Account-wide rate limit did not return Retry-After');
}
AuthTotpTestClock::$timestamp += 901;

$recoveryStart = $slim->handle($request('POST', '/api/auth/login', [
    'email' => $email, 'password' => $password,
]));
$recoveryLogin = totpHttpAssert(202, $recoveryStart, 'password step before recovery');
$recoveryCookie = totpHttpCookie($recoveryStart, $challengeCookieName);
$recovery = $slim->handle($request('POST', '/api/auth/totp/recovery', [
    'flowId' => $recoveryLogin['flowId'], 'recoveryCode' => $recoveryCode,
], $recoveryCookie));
$recoveryBody = totpHttpAssert(200, $recovery, 'start recovery');
$recoveryCookie = totpHttpCookie($recovery, $challengeCookieName);
AuthTotpTestClock::$timestamp += 30;
$recoveryProof = [
    'flowId' => $recoveryBody['flowId'], 'code' => totpHttpCode($recoveryBody['manualKey'], AuthTotpTestClock::$timestamp),
];
$badConfigFile = "$tmpDir/test-auth-totp-$testId-bad-config.php";
file_put_contents($badConfigFile, '<?php $config = require ' . var_export($configFile, true)
    . '; unset($config["settings"]["login_cookie"]["key"]); return $config;');
$badConfig = (new App($badConfigFile))->getSlimApp();
$failedRecovery = $badConfig->handle($request('POST', '/api/auth/totp/recovery/confirm', $recoveryProof, $recoveryCookie));
totpHttpAssert(503, $failedRecovery, 'recovery must preserve the pending flow when session cookie config is invalid');
$recoverConfirm = $slim->handle($request('POST', '/api/auth/totp/recovery/confirm', $recoveryProof, $recoveryCookie));
$replacement = totpHttpAssert(200, $recoverConfirm, 'confirm recovery');
if (($replacement['reauthenticationRequired'] ?? false) !== true || empty($replacement['recoveryCode'])) {
    totpHttpFail('Recovery confirmation did not rotate factor and recovery code');
}

// The old session was revoked at recovery commit. A fresh login uses the replacement seed.
AuthTotpTestClock::$timestamp += 30;
$postRecoveryStart = $slim->handle($request('POST', '/api/auth/login', [
    'email' => $email, 'password' => $password,
]));
$postRecoveryChallenge = totpHttpAssert(202, $postRecoveryStart, 'login after recovery');
$postRecoveryCookie = totpHttpCookie($postRecoveryStart, $challengeCookieName);
$replacementSeed = $recoveryBody['manualKey'];
$postRecoveryLogin = $slim->handle($request('POST', '/api/auth/login/totp', [
    'flowId' => $postRecoveryChallenge['flowId'], 'code' => totpHttpCode($replacementSeed, AuthTotpTestClock::$timestamp),
], $postRecoveryCookie));
totpHttpAssert(200, $postRecoveryLogin, 'TOTP login with replacement factor');
$sessionCookie = totpHttpCookie($postRecoveryLogin, $loginCookieName);

// Disable requires both password and current factor, and closes the account back to password-only.
AuthTotpTestClock::$timestamp += 30;
$disable = $slim->handle($request('POST', '/api/auth/totp/disable', [
    'currentPassword' => $password, 'code' => totpHttpCode($replacementSeed, AuthTotpTestClock::$timestamp),
], $sessionCookie));
$disabled = totpHttpAssert(200, $disable, 'disable TOTP');
if (($disabled['enabled'] ?? true) !== false || ($disabled['reauthenticationRequired'] ?? false) !== true) {
    totpHttpFail('Disable did not clear TOTP and require reauthentication');
}

fwrite(STDOUT, "TOTP HTTP lifecycle, replay, expiry, and origin checks passed ($driver).\n");
