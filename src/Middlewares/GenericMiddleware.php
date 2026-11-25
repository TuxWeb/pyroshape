<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;

abstract class GenericMiddleware
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $this->logger->debug('MW: ' . static::class . ' BEGIN');
        $response = $this->process($request, $handler);
        $this->logger->debug('MW: ' . static::class . ' END');

        return $response;
    }

    abstract public function process(Request $request, RequestHandler $handler): Response;
}
