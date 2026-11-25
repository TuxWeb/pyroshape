<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Middleware\ContentLengthMiddleware;

class ContentLength extends GenericMiddleware implements MiddlewareInterface
{
    private ContentLengthMiddleware $middleware;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->middleware = new ContentLengthMiddleware();
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        return $this->middleware->process($request, $handler);
    }
}
