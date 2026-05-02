<?php

declare(strict_types=1);

namespace Dam2k\Pyroshape\Core;

use Dam2k\Pyroshape\Core\Controllers\AuthController;
use Dam2k\Pyroshape\Core\Controllers\HealthController;
use Dam2k\Pyroshape\Core\Database\PdoFactory;
use Dam2k\Pyroshape\Core\Exceptions\ConfigNotValidException;
use Dam2k\Pyroshape\Core\Exceptions\JsonErrorHandler;
use Dam2k\Pyroshape\Core\Logger\AccessLogger;
use Dam2k\Pyroshape\Core\Logger\AppLogger;
use Dam2k\Pyroshape\Core\Middlewares\AddDefaultHeaders;
use Dam2k\Pyroshape\Core\Middlewares\ContentLength;
use Dam2k\Pyroshape\Core\Middlewares\RemoveTrailingSlash;
use Dam2k\Pyroshape\Core\Middlewares\RequestLogger;
use DI\Bridge\Slim\Bridge;
use DI\Container;
use DI\ContainerBuilder;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\App as SlimApp;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;

class App
{
    private Container $container;
    private SlimApp $slimApp;

    public function __construct(string $config)
    {
        $this->container = $this->initializeContainer($config);
        $this->validateSecuritySettings();
        $this->slimApp = Bridge::create($this->container);

        $basePath = $this->container->get('settings')['basePath'];
        if ($basePath !== '' && str_ends_with($basePath, '/')) {
            $message = 'Remove trailing slash from basePath in config/config.php';
            $this->container->get(LoggerInterface::class)->error($message);
            throw new ConfigNotValidException($message);
        }

        if ($basePath !== '') {
            $this->slimApp->setBasePath($basePath);
        }

        $this->setupMiddleware();
        $this->setupRoutes();
    }

    public function getSlimApp(): SlimApp
    {
        return $this->slimApp;
    }

    public function run(): void
    {
        $this->slimApp->run();
    }

    private function initializeContainer(string $config): Container
    {
        $configFile = stream_resolve_include_path($config);
        if (!$configFile || !is_readable($configFile)) {
            throw new ConfigNotValidException("Configuration file \"$config\" cannot be found or read");
        }

        $builder = new ContainerBuilder();
        $containerCacheKey = sha1(implode(':', [
            $configFile,
            (string) file_get_contents($configFile),
            (string) file_get_contents(__FILE__),
        ]));
        $appRoot = dirname($configFile);
        if (basename($appRoot) === 'config') {
            $appRoot = dirname($appRoot);
        }

        $containerCacheDir = $appRoot . '/tmp/container/' . $containerCacheKey;
        if (!is_dir($containerCacheDir) && !mkdir($containerCacheDir, 0775, true) && !is_dir($containerCacheDir)) {
            throw new ConfigNotValidException(
                sprintf('Container cache directory "%s" cannot be created', $containerCacheDir)
            );
        }

        $compiledContainerClass = 'PyroshapeCompiledContainer' . substr($containerCacheKey, 0, 16);
        $builder->enableCompilation($containerCacheDir, $compiledContainerClass);
        $builder->writeProxiesToFile(true, $containerCacheDir . '/proxies');
        $builder->addDefinitions($configFile);
        $builder->addDefinitions([
            AppLogger::class => function (ContainerInterface $container): AppLogger {
                $settings = $container->get('settings')['logger'];
                $logger = new AppLogger($settings['name']);
                $formatter = new LineFormatter($settings['logFormat'], $settings['dateFormat']);
                $handler = new StreamHandler($settings['path'], $settings['level']);
                $handler->setFormatter($formatter);
                $logger->pushHandler($handler);
                $logger->pushProcessor(new UidProcessor());

                if (!empty($settings['timezone'])) {
                    $logger->setTimezone(new \DateTimeZone($settings['timezone']));
                }

                return $logger;
            },
            AccessLogger::class => function (ContainerInterface $container): AccessLogger {
                $settings = $container->get('settings')['accessLogger'];
                $logger = new AccessLogger($settings['name']);
                $formatter = new LineFormatter($settings['logFormat'], $settings['dateFormat']);
                $handler = new StreamHandler($settings['path'], $settings['level']);
                $handler->setFormatter($formatter);
                $logger->pushHandler($handler);

                if (!empty($settings['timezone'])) {
                    $logger->setTimezone(new \DateTimeZone($settings['timezone']));
                }

                return $logger;
            },
            LoggerInterface::class => fn (ContainerInterface $container): AppLogger => $container->get(AppLogger::class),
            PDO::class => fn (ContainerInterface $container): PDO => (new PdoFactory())($container),
            ContainerInterface::class => fn (ContainerInterface $container): ContainerInterface => $container,
            ResponseFactoryInterface::class => fn (): ResponseFactory => new ResponseFactory(),
        ]);

        return $builder->build();
    }

    private function validateSecuritySettings(): void
    {
        $settings = $this->container->get('settings');
        $environment = (string) ($settings['environment'] ?? 'development');
        if ($environment === 'development') {
            return;
        }

        $unsafeSettings = [];
        $keySettings = [
            'jwt_refresh_token.key' => $settings['jwt_refresh_token']['key'] ?? null,
            'jwt_refresh_token.secret' => $settings['jwt_refresh_token']['secret'] ?? null,
            'jwt_access_token.key' => $settings['jwt_access_token']['key'] ?? null,
            'jwt_access_token.secret' => $settings['jwt_access_token']['secret'] ?? null,
            'login_cookie.key' => $settings['login_cookie']['key'] ?? null,
            'login_cookie.secret' => $settings['login_cookie']['secret'] ?? null,
            'login_cookie.salt' => $settings['login_cookie']['salt'] ?? null,
        ];

        foreach ($keySettings as $name => $value) {
            if (is_string($value) && str_starts_with($value, 'change-me-')) {
                $unsafeSettings[] = $name;
            }
        }

        if (($settings['emailVerification']['devExposeToken'] ?? false) === true) {
            $unsafeSettings[] = 'emailVerification.devExposeToken';
        }

        if ($unsafeSettings === []) {
            return;
        }

        $message = 'Unsafe development security settings cannot be used outside development: '
            . implode(', ', $unsafeSettings);
        $this->container->get(LoggerInterface::class)->error($message);
        throw new ConfigNotValidException($message);
    }

    private function setupMiddleware(): void
    {
        $this->slimApp->add(ContentLength::class);
        $this->slimApp->addBodyParsingMiddleware();
        $this->slimApp->addRoutingMiddleware();
        $this->slimApp->add(RemoveTrailingSlash::class);
        $this->slimApp->add(AddDefaultHeaders::class);
        $this->slimApp->add(RequestLogger::class);

        $errorHandler = new JsonErrorHandler(
            $this->slimApp->getCallableResolver(),
            $this->slimApp->getResponseFactory(),
            $this->container
        );

        $settings = $this->container->get('settings');
        $errorMiddleware = $this->slimApp->addErrorMiddleware($settings['displayErrorDetails'], true, true);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
    }

    private function setupRoutes(): void
    {
        $this->slimApp->get('/health', [HealthController::class, 'getHealth'])->setName('health');
        $this->slimApp->post('/api/auth/register', [AuthController::class, 'postRegister'])->setName('auth.register');
        $this->slimApp->get('/api/auth/verify-email', [AuthController::class, 'getVerifyEmail'])
            ->setName('auth.verify-email');
        $this->slimApp->post('/api/auth/login', [AuthController::class, 'postLogin'])->setName('auth.login');
        $this->slimApp->post('/api/auth/logout', [AuthController::class, 'postLogout'])->setName('auth.logout');
        $this->slimApp->get('/api/session', [AuthController::class, 'getSession'])->setName('session');

        $this->setupAppRoutes();
        $this->setupSpaRoutes();
    }

    protected function setupAppRoutes(): void
    {
    }

    private function setupSpaRoutes(): void
    {
        $this->slimApp->get('/_app/{asset:.*}', function (Request $request, Response $response, string $asset): Response {
            return $this->serveSpaAsset($request, $response, '_app/' . $asset);
        })->setName('spa.root-asset');
        $this->slimApp->get('/app/{asset:.*}', function (Request $request, Response $response, string $asset): Response {
            return $this->serveSpaAsset($request, $response, $asset);
        })->setName('spa.asset');

        $spaFallback = fn (Request $request, Response $response): Response => $this->serveSpaFallback(
            $request,
            $response
        );
        $this->slimApp->get('/', $spaFallback)->setName('spa.root');
        $this->slimApp->get('/{path:.*}', $spaFallback)->setName('spa.fallback');
    }

    private function serveSpaFallback(Request $request, Response $response): Response
    {
        $path = ltrim($request->getUri()->getPath(), '/');
        $basePath = trim((string) ($this->container->get('settings')['basePath'] ?? ''), '/');

        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath) + 1);
        }

        if ($path === 'api' || str_starts_with($path, 'api/') || $path === 'health' || str_starts_with($path, 'health/')) {
            throw new HttpNotFoundException($request);
        }

        $indexFile = $this->frontendAppPath() . '/index.html';
        if (!is_file($indexFile) || !is_readable($indexFile)) {
            throw new HttpNotFoundException($request);
        }

        $response->getBody()->write((string) file_get_contents($indexFile));

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    private function serveSpaAsset(Request $request, Response $response, string $asset): Response
    {
        $asset = ltrim($asset, '/');
        $baseDir = realpath($this->frontendAppPath());
        $file = $baseDir !== false ? realpath($baseDir . '/' . $asset) : false;

        if (
            $asset === ''
            || $baseDir === false
            || $file === false
            || !is_file($file)
            || !str_starts_with($file, $baseDir . DIRECTORY_SEPARATOR)
        ) {
            throw new HttpNotFoundException($request);
        }

        $response->getBody()->write((string) file_get_contents($file));

        return $response
            ->withHeader('Content-Type', $this->contentTypeForFile($file))
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }

    private function contentTypeForFile(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css; charset=UTF-8',
            'html' => 'text/html; charset=UTF-8',
            'js', 'mjs' => 'text/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'map' => 'application/json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };
    }

    private function frontendAppPath(): string
    {
        $settings = $this->container->get('settings');
        $path = $settings['frontend']['appPath'] ?? __DIR__ . '/../public/app';

        return rtrim((string) $path, '/');
    }
}
