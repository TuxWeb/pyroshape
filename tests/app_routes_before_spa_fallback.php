<?php

declare(strict_types=1);

use Dam2k\Pyroshape\Core\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Factory\ServerRequestFactory;

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

final class RouteHookTestApp extends App
{
    protected function setupAppRoutes(): void
    {
        $this->getSlimApp()->get('/api/demo/ping', function (Request $request, Response $response): Response {
            $response->getBody()->write(json_encode(['pong' => true], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });
    }
}

$tmpDir = sys_get_temp_dir() . '/pyroshape-app-route-hook-' . bin2hex(random_bytes(8));
$configDir = $tmpDir . '/config';
$frontendDir = $tmpDir . '/frontend';
$logDir = $tmpDir . '/logs';

mkdir($configDir, 0775, true);
mkdir($frontendDir, 0775, true);
mkdir($logDir, 0775, true);
file_put_contents($frontendDir . '/index.html', '<!doctype html><title>SPA</title>');

$configFile = $configDir . '/config.php';
$config = [
    'settings' => [
        'basePath' => '',
        'displayErrorDetails' => true,
        'environment' => 'development',
        'headers' => [],
        'frontend' => [
            'appPath' => $frontendDir,
        ],
        'logger' => [
            'name' => 'test',
            'path' => $logDir . '/app.log',
            'level' => 'debug',
            'logFormat' => "%message%\n",
            'dateFormat' => 'Y-m-d H:i:s',
        ],
        'accessLogger' => [
            'name' => 'access',
            'path' => $logDir . '/access.log',
            'level' => 'debug',
            'logFormat' => "%message%\n",
            'dateFormat' => 'Y-m-d H:i:s',
        ],
    ],
];
file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

$app = new RouteHookTestApp($configFile);
$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/demo/ping');
$response = $app->getSlimApp()->handle($request);
$body = (string) $response->getBody();
$payload = json_decode($body, true);

if ($response->getStatusCode() !== 200) {
    throw new RuntimeException('Expected status 200, got ' . $response->getStatusCode() . ' with body: ' . $body);
}

if (!str_starts_with($response->getHeaderLine('Content-Type'), 'application/json')) {
    throw new RuntimeException('Expected JSON content-type, got: ' . $response->getHeaderLine('Content-Type'));
}

if (!is_array($payload) || ($payload['pong'] ?? null) !== true) {
    throw new RuntimeException('Expected pong true JSON response, got: ' . $body);
}

echo "app routes before spa fallback ok\n";
