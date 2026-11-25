<?php

declare(strict_types=1);

use TuxWeb\Pyroshape\Core\App;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

require __DIR__ . '/../vendor/autoload.php';

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

function assertStatus(ResponseInterface $response, int $expected, string $label): void
{
    if ($response->getStatusCode() !== $expected) {
        fwrite(STDERR, "$label: expected status $expected, got {$response->getStatusCode()}\n");
        fwrite(STDERR, "Response body: " . (string) $response->getBody() . "\n");
        exit(1);
    }
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
    exit(1);
}

$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$testId = bin2hex(random_bytes(6));
$sqliteFile = "$tmpDir/test-auth-password-change-$testId.sqlite";
$configFile = "$tmpDir/test-auth-password-change-$testId-config.php";
$loginCookieName = 'pyroshape_password_change';

$config = [
    'settings' => [
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapePasswordChangeTest',
            'path' => "$tmpDir/test-auth-password-change-$testId-app.log",
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'PyroshapePasswordChangeTest',
            'path' => "$tmpDir/test-auth-password-change-$testId-access.log",
            'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => [],
        'database' => [
            'driver' => 'sqlite',
            'dsn' => 'sqlite:' . $sqliteFile,
            'path' => $sqliteFile,
        ],
        'auth' => [
            'sessionMode' => 'stateful',
        ],
        'jwt_refresh_token' => [
            'secret' => hash('sha256', 'password-change-refresh-secret'),
            'ttl' => 60 * 60 * 24 * 30,
            'algorithm' => 'HS256',
        ],
        'jwt_access_token' => [
            'secret' => hash('sha256', 'password-change-access-secret'),
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
            'key' => hash('sha256', 'password-change-cookie-key'),
            'salt' => 'password-change-cookie-salt',
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

register_shutdown_function(static function () use ($tmpDir, $testId): void {
    foreach (glob("$tmpDir/test-auth-password-change-$testId-*") ?: [] as $file) {
        @unlink($file);
    }
});

$slim = (new App($configFile))->getSlimApp();
$email = "password-change-$testId@example.test";
$oldPassword = 'CorrectHorseBatteryStaple!42';
$newPassword = 'NewCorrectHorseBatteryStaple!42';

$registerResponse = $slim->handle(jsonRequest('POST', '/api/auth/register', [
    'email' => $email,
    'password' => $oldPassword,
]));
assertStatus($registerResponse, 201, 'register');
$verificationToken = responseJson($registerResponse)['verificationToken'] ?? '';

$verifyResponse = $slim->handle(jsonRequest('GET', '/api/auth/verify-email?token=' . rawurlencode((string) $verificationToken)));
assertStatus($verifyResponse, 200, 'verify');

$loginA = $slim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $oldPassword,
]));
assertStatus($loginA, 200, 'first login');
$cookieA = extractCookie($loginA, $loginCookieName, 'first login');

$loginB = $slim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $oldPassword,
]));
assertStatus($loginB, 200, 'second login');
$cookieB = extractCookie($loginB, $loginCookieName, 'second login');

$unauthenticatedChange = $slim->handle(jsonRequest('POST', '/api/auth/change-password', [
    'currentPassword' => $oldPassword,
    'newPassword' => $newPassword,
    'confirmPassword' => $newPassword,
]));
assertStatus($unauthenticatedChange, 401, 'unauthenticated change');

$wrongCurrent = $slim->handle(jsonRequest('POST', '/api/auth/change-password', [
    'currentPassword' => 'wrong-password',
    'newPassword' => $newPassword,
    'confirmPassword' => $newPassword,
], $cookieA));
assertStatus($wrongCurrent, 400, 'wrong current password');

$mismatch = $slim->handle(jsonRequest('POST', '/api/auth/change-password', [
    'currentPassword' => $oldPassword,
    'newPassword' => $newPassword,
    'confirmPassword' => $newPassword . '-mismatch',
], $cookieA));
assertStatus($mismatch, 400, 'password confirmation mismatch');

$tooShort = $slim->handle(jsonRequest('POST', '/api/auth/change-password', [
    'currentPassword' => $oldPassword,
    'newPassword' => 'short',
    'confirmPassword' => 'short',
], $cookieA));
assertStatus($tooShort, 400, 'short new password');

$samePassword = $slim->handle(jsonRequest('POST', '/api/auth/change-password', [
    'currentPassword' => $oldPassword,
    'newPassword' => $oldPassword,
    'confirmPassword' => $oldPassword,
], $cookieA));
assertStatus($samePassword, 400, 'same new password');

$change = $slim->handle(jsonRequest('POST', '/api/auth/change-password', [
    'currentPassword' => $oldPassword,
    'newPassword' => $newPassword,
    'confirmPassword' => $newPassword,
], $cookieA));
assertStatus($change, 200, 'change password');
$payload = responseJson($change);
if (($payload['changed'] ?? false) !== true || ($payload['authenticated'] ?? false) !== true) {
    fwrite(STDERR, "Unexpected change password payload: " . json_encode($payload) . "\n");
    exit(1);
}

$sessionA = $slim->handle(jsonRequest('GET', '/api/session', null, $cookieA));
assertStatus($sessionA, 200, 'current session after change');
if ((responseJson($sessionA)['authenticated'] ?? false) !== true) {
    fwrite(STDERR, "Current session was not kept active\n");
    exit(1);
}

$sessionB = $slim->handle(jsonRequest('GET', '/api/session', null, $cookieB));
assertStatus($sessionB, 200, 'other session after change');
if ((responseJson($sessionB)['authenticated'] ?? true) !== false) {
    fwrite(STDERR, "Other session was not revoked\n");
    exit(1);
}

$oldLogin = $slim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $oldPassword,
]));
assertStatus($oldLogin, 401, 'old password login');

$newLogin = $slim->handle(jsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $newPassword,
]));
assertStatus($newLogin, 200, 'new password login');

echo "auth password change ok\n";
