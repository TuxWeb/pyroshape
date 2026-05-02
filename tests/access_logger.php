<?php

declare(strict_types=1);

use Dam2k\PyroshapeSkeleton\App;
use Monolog\Logger;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';

$appLog = __DIR__ . '/../tmp/test-app.log';
$accessLog = __DIR__ . '/../tmp/test-access.log';
@unlink($appLog);
@unlink($accessLog);

$config = [
    'settings' => [
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapeTest',
            'path' => $appLog,
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %level_name% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'PyroshapeTest',
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
    ],
];

$configFile = __DIR__ . '/../tmp/test-access-logger-config.php';
file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

$app = new App($configFile);
$request = (new ServerRequestFactory())->createServerRequest('GET', '/health', ['REMOTE_ADDR' => '127.0.0.1:54321'])
    ->withHeader('User-Agent', 'PyroshapeSmoke/1.0');
$response = $app->getSlimApp()->handle($request);

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected status 200, got {$response->getStatusCode()}\n");
    exit(1);
}

$appLogContents = is_file($appLog) ? file_get_contents($appLog) : '';
$accessLogContents = is_file($accessLog) ? file_get_contents($accessLog) : '';

if (!str_contains($accessLogContents, '127.0.0.1 "GET /health HTTP/1.1" 200 (OK) "PyroshapeSmoke/1.0"')) {
    fwrite(STDERR, "Access log missing request line:\n$accessLogContents\n");
    exit(1);
}

if (str_contains($appLogContents, '"GET /health HTTP/1.1"')) {
    fwrite(STDERR, "Application log contains access log line:\n$appLogContents\n");
    exit(1);
}

echo "access logger ok\n";
