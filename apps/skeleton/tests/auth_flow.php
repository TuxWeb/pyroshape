<?php

declare(strict_types=1);

use TuxWeb\PyroshapeSkeleton\App;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param array<string, mixed>|null $payload
 */
function jsonRequest(string $method, string $uri, ?array $payload = null, ?string $cookie = null): ServerRequestInterface
{
    $request = (new ServerRequestFactory())->createServerRequest($method, $uri)
        ->withHeader('Accept', 'application/json');

    if ($payload !== null) {
        $body = (new StreamFactory())->createStream(json_encode($payload, JSON_THROW_ON_ERROR));
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    if ($cookie !== null && $cookie !== '') {
        $request = $request
            ->withHeader('Cookie', $cookie)
            ->withCookieParams(parseCookieHeader($cookie));
    }

    return $request;
}

/**
 * @param array<string, string> $headers
 */
function jsonRequestWithHeaders(
    string $method,
    string $uri,
    ?array $payload = null,
    ?string $cookie = null,
    array $headers = []
): ServerRequestInterface {
    $request = jsonRequest($method, $uri, $payload, $cookie);

    foreach ($headers as $name => $value) {
        $request = $request->withHeader($name, $value);
    }

    return $request;
}

/**
 * @return array<string, string>
 */
function parseCookieHeader(string $cookie): array
{
    $cookies = [];

    foreach (explode(';', $cookie) as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $part, 2);
        $cookies[$name] = urldecode($value);
    }

    return $cookies;
}

/**
 * @return array<string, mixed>
 */
function responseJson(ResponseInterface $response): array
{
    $body = (string) $response->getBody();
    $payload = json_decode($body, true);

    if (!is_array($payload)) {
        fwrite(STDERR, "Expected JSON object response, got: $body\n");
        exit(1);
    }

    return $payload;
}

/**
 * @param list<int> $expectedStatuses
 */
function assertStatusIn(ResponseInterface $response, array $expectedStatuses, string $label): void
{
    $actual = $response->getStatusCode();
    if (!in_array($actual, $expectedStatuses, true)) {
        $expected = implode(' or ', $expectedStatuses);
        fwrite(STDERR, "$label: expected status $expected, got $actual\n");
        fwrite(STDERR, "Response body: " . (string) $response->getBody() . "\n");
        exit(1);
    }
}

/**
 * @param array<string, mixed> $payload
 */
function assertPayloadValue(array $payload, string $key, mixed $expected, string $label): void
{
    if (($payload[$key] ?? null) !== $expected) {
        fwrite(STDERR, "$label: expected JSON key \"$key\" to be " . var_export($expected, true) . "\n");
        fwrite(STDERR, 'Payload: ' . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
}

/**
 * @param array<string, mixed> $payload
 */
function assertPayloadString(array $payload, string $key, string $label): string
{
    if (!isset($payload[$key]) || !is_string($payload[$key]) || $payload[$key] === '') {
        fwrite(STDERR, "$label: expected JSON key \"$key\" to contain a non-empty string\n");
        fwrite(STDERR, 'Payload: ' . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }

    return $payload[$key];
}

function extractCookie(ResponseInterface $response, string $cookieName, string $label): string
{
    foreach ($response->getHeader('Set-Cookie') as $header) {
        $cookiePair = explode(';', $header, 2)[0];
        if (str_starts_with($cookiePair, $cookieName . '=')) {
            return $cookiePair;
        }
    }

    fwrite(STDERR, "$label: expected Set-Cookie header for \"$cookieName\"\n");
    fwrite(STDERR, 'Set-Cookie headers: ' . json_encode($response->getHeader('Set-Cookie')) . "\n");
    exit(1);
}

$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$testId = bin2hex(random_bytes(6));
$appLog = "$tmpDir/test-auth-flow-$testId-app.log";
$accessLog = "$tmpDir/test-auth-flow-$testId-access.log";
$sqliteFile = "$tmpDir/test-auth-flow-$testId.sqlite";
$configFile = "$tmpDir/test-auth-flow-$testId-config.php";
$loginCookieName = 'pyroshape_session';

$config = [
    'settings' => [
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapeAuthFlowTest',
            'path' => $appLog,
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% [%channel%.%level_name% %extra%] %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'PyroshapeAuthFlowTest',
            'path' => $accessLog,
            'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => [
            'X-Pyroshape' => '1',
            'X-Robots-Tag' => 'noindex, nofollow',
        ],
        'database' => [
            'driver' => 'sqlite',
            'dsn' => 'sqlite:' . $sqliteFile,
            'path' => $sqliteFile,
        ],
        'jwt_refresh_token' => [
            'secret' => hash('sha256', 'auth-flow-refresh-secret'),
            'ttl' => 60 * 60 * 24 * 30,
            'algorithm' => 'HS256',
        ],
        'jwt_access_token' => [
            'secret' => hash('sha256', 'auth-flow-access-secret'),
            'ttl' => 60 * 15,
            'algorithm' => 'HS256',
        ],
        'login_cookie' => [
            'name' => $loginCookieName,
            'ttl' => 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => false,
            'httpOnly' => true,
            'sameSite' => 'Lax',
            'key' => hash('sha256', 'auth-flow-cookie-key'),
            'salt' => 'auth-flow-cookie-salt',
        ],
        'emailVerification' => [
            'devExposeToken' => true,
        ],
    ],
];

file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

$pdo = new PDO('sqlite:' . $sqliteFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    pwdhash TEXT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    email_verified INTEGER NOT NULL DEFAULT 0,
    nickname TEXT NULL,
    name TEXT NULL,
    surname TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE auth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    users_id INTEGER NOT NULL,
    jti TEXT NOT NULL UNIQUE,
    token_hash TEXT NOT NULL,
    released TEXT NOT NULL,
    expiration TEXT NOT NULL,
    revocation_time TEXT NULL
);
CREATE TABLE email_verification_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    users_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    expiration TEXT NOT NULL,
    used_at TEXT NULL
);
SQL);

register_shutdown_function(static function () use ($appLog, $accessLog, $sqliteFile, $configFile, $tmpDir, $testId): void {
    @unlink($appLog);
    @unlink($accessLog);
    @unlink($sqliteFile);
    @unlink($configFile);
    @unlink("$tmpDir/test-auth-flow-$testId-bad-cookie-config.php");
});

$slim = (new App($configFile))->getSlimApp();
$email = "auth-flow-$testId@example.test";
$password = 'CorrectHorseBatteryStaple!42';
$nickname = 'Auth Flow';

$registerResponse = $slim->handle(jsonRequest('POST', '/api/auth/register', [
    'email' => $email,
    'password' => $password,
    'nickname' => $nickname,
]));
assertStatusIn($registerResponse, [201, 200], 'register');
$registerPayload = responseJson($registerResponse);
$verificationToken = assertPayloadString($registerPayload, 'verificationToken', 'register');

$verifyResponse = $slim->handle(jsonRequest('GET', '/api/auth/verify-email?token=' . rawurlencode($verificationToken)));
assertStatusIn($verifyResponse, [200], 'verify email');
$verifyPayload = responseJson($verifyResponse);
assertPayloadValue($verifyPayload, 'verified', true, 'verify email');

$loginResponse = $slim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $password,
]));
assertStatusIn($loginResponse, [200], 'login');
$sessionCookie = extractCookie($loginResponse, $loginCookieName, 'login');

$sessionResponse = $slim->handle(jsonRequest('GET', '/api/session', null, $sessionCookie));
assertStatusIn($sessionResponse, [200], 'session after login');
$sessionPayload = responseJson($sessionResponse);
assertPayloadValue($sessionPayload, 'authenticated', true, 'session after login');
assertPayloadValue($sessionPayload, 'email', $email, 'session after login');

$hostileLogoutResponse = $slim->handle(jsonRequestWithHeaders(
    'POST',
    'http://localhost/api/auth/logout',
    null,
    $sessionCookie,
    ['Origin' => 'https://evil.example.test']
));
assertStatusIn($hostileLogoutResponse, [403], 'logout hostile origin');

$sameOriginLogoutResponse = $slim->handle(jsonRequestWithHeaders(
    'POST',
    'http://localhost/api/auth/logout',
    null,
    $sessionCookie,
    ['Origin' => 'http://localhost']
));
assertStatusIn($sameOriginLogoutResponse, [200], 'logout same origin');

$loginResponse = $slim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $password,
]));
assertStatusIn($loginResponse, [200], 'login before default-port origin');
$sessionCookie = extractCookie($loginResponse, $loginCookieName, 'login before default-port origin');

$defaultPortOriginLogoutResponse = $slim->handle(jsonRequestWithHeaders(
    'POST',
    '/api/auth/logout',
    null,
    $sessionCookie,
    [
        'Host' => 'localhost:80',
        'Origin' => 'http://localhost',
    ]
));
assertStatusIn($defaultPortOriginLogoutResponse, [200], 'logout default-port origin');

$loginResponse = $slim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $password,
]));
assertStatusIn($loginResponse, [200], 'login before case-insensitive origin');
$sessionCookie = extractCookie($loginResponse, $loginCookieName, 'login before case-insensitive origin');

$caseInsensitiveOriginLogoutResponse = $slim->handle(jsonRequestWithHeaders(
    'POST',
    'http://localhost/api/auth/logout',
    null,
    $sessionCookie,
    ['Origin' => 'http://LOCALHOST']
));
assertStatusIn($caseInsensitiveOriginLogoutResponse, [200], 'logout case-insensitive origin');

$loginResponse = $slim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $password,
]));
assertStatusIn($loginResponse, [200], 'second login');
$sessionCookie = extractCookie($loginResponse, $loginCookieName, 'second login');

$badCookieConfig = $config;
unset($badCookieConfig['settings']['login_cookie']['key'], $badCookieConfig['settings']['login_cookie']['salt']);
$badCookieConfigFile = "$tmpDir/test-auth-flow-$testId-bad-cookie-config.php";
file_put_contents($badCookieConfigFile, '<?php return ' . var_export($badCookieConfig, true) . ';');
$badCookieSlim = (new App($badCookieConfigFile))->getSlimApp();
$badCookieLoginResponse = $badCookieSlim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $password,
]));
assertStatusIn($badCookieLoginResponse, [500], 'login cookie config failure');

$logoutResponse = $slim->handle(jsonRequest('POST', '/api/auth/logout', null, $sessionCookie));
assertStatusIn($logoutResponse, [200], 'logout');
$clearedCookie = extractCookie($logoutResponse, $loginCookieName, 'logout');

$loggedOutSessionResponse = $slim->handle(jsonRequest('GET', '/api/session', null, $clearedCookie !== '' ? $clearedCookie : $sessionCookie));
assertStatusIn($loggedOutSessionResponse, [200], 'session after logout');
$loggedOutSessionPayload = responseJson($loggedOutSessionResponse);
assertPayloadValue($loggedOutSessionPayload, 'authenticated', false, 'session after logout');

echo "auth flow ok\n";
