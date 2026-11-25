<?php

declare(strict_types=1);

use TuxWeb\PyroshapeDemo\App;
use TuxWeb\PyroshapeDemo\Exceptions\ConfigNotValidException;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/fixtures/runtime_environment.php';

$app = new App(__DIR__ . '/../config/config.php');
$slim = $app->getSlimApp();
$request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
$response = $slim->handle($request);

$body = (string) $response->getBody();
$payload = json_decode($body, true);

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected status 200, got {$response->getStatusCode()}\n");
    exit(1);
}

if ($payload !== ['status' => 'ok', 'service' => 'pyroshape']) {
    fwrite(STDERR, "Unexpected health payload: $body\n");
    exit(1);
}

$rotatedKey = bin2hex(random_bytes(32));
putenv('PYROSHAPE_JWT_ACCESS_KEY=' . $rotatedKey);
putenv('PYROSHAPE_ENVIRONMENT');

$production = new App(__DIR__ . '/../config/config.php');
$settings = $production->getSlimApp()->getContainer()->get('settings');
if (
    $settings['jwt_access_token']['key'] !== $rotatedKey
    || !$settings['login_cookie']['cookieopts']['secure']
    || $settings['emailVerification']['devExposeToken']
) {
    fwrite(STDERR, "Expected current environment values and secure production settings\n");
    exit(1);
}


$unsafeConfigFile = __DIR__ . '/../tmp/test-unsafe-secret-config.php';
file_put_contents($unsafeConfigFile, '<?php return ' . var_export([
    'settings' => [
        'environment' => 'production',
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapeUnsafeSecretTest',
            'path' => __DIR__ . '/../tmp/test-unsafe-secret-app.log',
            'level' => Monolog\Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %level_name% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'PyroshapeUnsafeSecretTest',
            'path' => __DIR__ . '/../tmp/test-unsafe-secret-access.log',
            'level' => Monolog\Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => [],
        'jwt_refresh_token' => [
            'secret' => 'change-me-refresh-secret',
        ],
        'jwt_access_token' => [
            'secret' => 'change-me-access-secret',
        ],
        'login_cookie' => [
            'secret' => 'change-me-cookie-secret',
            'salt' => 'change-me-cookie-salt',
        ],
        'emailVerification' => [
            'devExposeToken' => false,
        ],
    ],
], true) . ';');

try {
    new App($unsafeConfigFile);
} catch (ConfigNotValidException $exception) {
    $message = $exception->getMessage();
    foreach (['jwt_refresh_token.secret', 'jwt_access_token.secret', 'login_cookie.secret', 'login_cookie.salt'] as $setting) {
        if (!str_contains($message, $setting)) {
            fwrite(STDERR, "Unsafe config error missing $setting: $message\n");
            exit(1);
        }
    }

    @unlink($unsafeConfigFile);
    @unlink(__DIR__ . '/../tmp/test-unsafe-secret-app.log');
    @unlink(__DIR__ . '/../tmp/test-unsafe-secret-access.log');
    echo "smoke ok\n";
    exit(0);
}

fwrite(STDERR, "Production unsafe secret aliases were accepted\n");
exit(1);
