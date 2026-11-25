<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Middlewares;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;

class AddDefaultHeaders extends GenericMiddleware implements MiddlewareInterface
{
    public function __construct(private ContainerInterface $container, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        foreach ($this->container->get('settings')['headers'] as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        if (!$response->hasHeader('Content-Type')) {
            $response = $response->withHeader('Content-Type', 'application/json');
        }

        return $response;
    }
}
