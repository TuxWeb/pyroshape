<?php

declare(strict_types=1);

namespace Dam2k\Pyroshape\Core\Middlewares;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class RemoveTrailingSlash extends GenericMiddleware implements MiddlewareInterface
{
    public function __construct(private ContainerInterface $container, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $basePath = $this->container->get('settings')['basePath'];

        if ($path !== $basePath . '/' && str_ends_with($path, '/')) {
            $newUri = $uri->withPath(rtrim($path, '/'));

            if ($request->getMethod() === 'GET') {
                $this->logger->info("Redirecting without trailing slash: $uri => $newUri");

                return (new ResponseFactory())
                    ->createResponse()
                    ->withHeader('Location', (string) $newUri)
                    ->withStatus(301);
            }

            $request = $request->withUri($newUri);
        }

        return $handler->handle($request);
    }
}
