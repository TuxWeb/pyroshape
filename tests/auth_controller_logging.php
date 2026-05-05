<?php

declare(strict_types=1);

use Dam2k\Pyroshape\Core\Auth\AuthException;
use Dam2k\Pyroshape\Core\Auth\AuthService;
use Dam2k\Pyroshape\Core\Controllers\AuthController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\AbstractLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

$autoloadCandidates = array_filter([
    getenv('PYROSHAPE_CORE_TEST_AUTOLOAD') ?: null,
    __DIR__ . '/../vendor/autoload.php',
    '/tmp/pyroshape-core-test-deps/vendor/autoload.php',
]);
$autoload = null;
foreach ($autoloadCandidates as $candidate) {
    if (is_readable($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    throw new RuntimeException('Composer autoload file not found. Run composer install or set PYROSHAPE_CORE_TEST_AUTOLOAD.');
}

require $autoload;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Dam2k\\Pyroshape\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function jsonRequest(string $path, array $body): ServerRequestInterface
{
    return (new ServerRequestFactory())
        ->createServerRequest('POST', $path)
        ->withParsedBody($body);
}

function assertLog(CapturingLogger $logger, string $level, string $message, array $contextSubset): void
{
    foreach ($logger->records as $record) {
        if ($record['level'] !== $level || $record['message'] !== $message) {
            continue;
        }

        foreach ($contextSubset as $key => $value) {
            if (($record['context'][$key] ?? null) !== $value) {
                continue 2;
            }
        }

        return;
    }

    throw new RuntimeException(sprintf(
        'Missing log %s %s with context subset %s. Records: %s',
        $level,
        $message,
        json_encode($contextSubset, JSON_THROW_ON_ERROR),
        json_encode($logger->records, JSON_THROW_ON_ERROR)
    ));
}

final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class LoggingTestAuthService extends AuthService
{
    /** @var array<string, mixed> */
    public array $registerPayload = [];
    /** @var array<string, mixed> */
    public array $verifyPayload = [];
    /** @var array<string, mixed> */
    public array $loginPayload = [];
    /** @var array<string, mixed> */
    public array $logoutPayload = [];
    /** @var array<string, mixed> */
    public array $sessionPayload = [];
    public ?AuthException $loginException = null;

    public function __construct()
    {
    }

    public function registerLocalUser(string $email, string $password, array $profile = []): array
    {
        return $this->registerPayload;
    }

    public function verifyEmailToken(string $token): array
    {
        return $this->verifyPayload;
    }

    public function login(string $email, string $password, ResponseInterface $response): array
    {
        if ($this->loginException !== null) {
            $exception = $this->loginException;
            $this->loginException = null;
            throw $exception;
        }

        return $this->loginPayload + ['response' => $response];
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return $this->logoutPayload + ['response' => $response];
    }

    public function getSession(ServerRequestInterface $request, ResponseInterface $response): array
    {
        return $this->sessionPayload + ['response' => $response];
    }
}

$logger = new CapturingLogger();
$auth = new LoggingTestAuthService();
$controller = new AuthController($auth, $logger);

$auth->registerPayload = [
    'id' => 42,
    'email' => 'user@example.test',
    'authenticated' => false,
];
$controller->postRegister(jsonRequest('/api/auth/register', [
    'email' => 'user@example.test',
    'password' => 'secret123',
]), new Response());
assertLog($logger, 'info', 'auth.registered', ['user_id' => 42, 'email' => 'user@example.test']);

$auth->verifyPayload = [
    'id' => 42,
    'email' => 'user@example.test',
    'verified' => true,
    'authenticated' => false,
];
$controller->getVerifyEmail(
    (new ServerRequestFactory())->createServerRequest('GET', '/api/auth/verify-email?token=valid'),
    new Response()
);
assertLog($logger, 'notice', 'auth.email_verified', ['user_id' => 42, 'email' => 'user@example.test']);

$auth->loginPayload = [
    'id' => 42,
    'email' => 'user@example.test',
    'authenticated' => true,
];
$controller->postLogin(jsonRequest('/api/auth/login', [
    'email' => 'user@example.test',
    'password' => 'secret123',
]), new Response());
assertLog($logger, 'info', 'auth.login_succeeded', ['user_id' => 42, 'email' => 'user@example.test']);

$auth->loginException = new AuthException('Invalid email or password', 401);
$controller->postLogin(jsonRequest('/api/auth/login', [
    'email' => 'missing@example.test',
    'password' => 'secret123',
]), new Response());
assertLog($logger, 'warning', 'auth.login_failed', ['email' => 'missing@example.test', 'status' => 401]);

$controller->postLogout(
    (new ServerRequestFactory())
        ->createServerRequest('POST', '/api/auth/logout')
        ->withHeader('Host', 'app.example.test')
        ->withHeader('Origin', 'https://evil.example.test'),
    new Response()
);
assertLog($logger, 'warning', 'auth.logout_forbidden_origin', ['origin' => 'https://evil.example.test']);

$auth->logoutPayload = [
    'authenticated' => false,
    'id' => 42,
    'email' => 'user@example.test',
];
$controller->postLogout(
    (new ServerRequestFactory())
        ->createServerRequest('POST', '/api/auth/logout')
        ->withHeader('Host', 'app.example.test')
        ->withHeader('Origin', 'http://app.example.test'),
    new Response()
);
assertLog($logger, 'info', 'auth.logout_succeeded', ['user_id' => 42, 'email' => 'user@example.test']);

$auth->sessionPayload = [
    'authenticated' => true,
    'id' => 42,
    'email' => 'user@example.test',
];
$controller->getSession(
    (new ServerRequestFactory())->createServerRequest('GET', '/api/session'),
    new Response()
);
assertLog($logger, 'debug', 'auth.session_authenticated', ['user_id' => 42, 'email' => 'user@example.test']);

echo "auth controller logging ok\n";
