<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Exceptions;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;

class JsonErrorHandler extends ErrorHandler
{
    private LoggerInterface $appLogger;

    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        private ContainerInterface $container
    ) {
        $this->appLogger = $container->get(LoggerInterface::class);
        parent::__construct($callableResolver, $responseFactory);
    }

    protected function logError(string $error): void
    {
        $exception = $this->exception;
        $request = $this->request;

        $this->appLogger->error(sprintf(
            '%s (%s on %s:%d): %s %s',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $request->getMethod(),
            $request->getUri()->getPath()
        ));
    }

    protected function respond(): \Psr\Http\Message\ResponseInterface
    {
        $exception = $this->exception;
        $statusCode = $this->statusCode;
        $payload = [
            'error' => [
                'message' => $statusCode === 404 ? 'Not found' : 'Internal server error',
            ],
        ];

        if ($this->displayErrorDetails) {
            $payload['error']['type'] = $exception::class;
            $payload['error']['detail'] = $exception->getMessage();
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
