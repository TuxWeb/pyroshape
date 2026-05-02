<?php

declare(strict_types=1);

namespace Dam2k\Pyroshape\Core\Middlewares\Auth;

use Dam2k\Pyroshape\Core\Auth\AuthService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class CheckValidAuthToken implements MiddlewareInterface
{
    public const UID_ATTRIBUTE = self::class . ':uid';

    public function __construct(
        private AuthService $auth,
        private ResponseFactoryInterface $responseFactory
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $session = $this->auth->getSession($request, $this->responseFactory->createResponse());
        $uid = ($session['authenticated'] ?? false) === true && isset($session['id'])
            ? (string) $session['id']
            : '';

        $response = $handler->handle($request->withAttribute(self::UID_ATTRIBUTE, $uid));
        $sessionResponse = $session['response'] ?? null;
        if (!$sessionResponse instanceof Response) {
            return $response;
        }

        foreach ($sessionResponse->getHeader('Set-Cookie') as $setCookie) {
            $response = $response->withAddedHeader('Set-Cookie', $setCookie);
        }

        return $response;
    }
}
