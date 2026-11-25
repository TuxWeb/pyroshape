<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Middlewares;

use TuxWeb\Pyroshape\Core\Logger\AccessLogger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LogLevel;

class RequestLogger implements MiddlewareInterface
{
    public const CLIENT_IP_ATTRIBUTE = self::class . ':clientIp';

    public function __construct(private AccessLogger $logger)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $clientIp = $this->extractIpAddress($request->getServerParams()['REMOTE_ADDR'] ?? '');
        $request = $request->withAttribute(self::CLIENT_IP_ATTRIBUTE, $clientIp);
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        $protocolVersion = $request->getProtocolVersion();
        $userAgent = $request->getHeaderLine('User-Agent');

        $response = $handler->handle($request);
        $status = $response->getStatusCode();
        $reason = $response->getReasonPhrase();
        $level = $status < 400 ? LogLevel::INFO : LogLevel::NOTICE;

        $this->logger->log(
            $level,
            sprintf(
                '%s "%s %s HTTP/%s" %d (%s) "%s"',
                $clientIp,
                $method,
                $uri,
                $protocolVersion,
                $status,
                $reason,
                $userAgent
            ),
            [
                'request' => [
                    'method' => $method,
                    'uri' => $uri,
                    'protocolVersion' => $protocolVersion,
                    'remote' => $clientIp,
                ],
                'response' => [
                    'status' => $status,
                    'reason' => $reason,
                ],
            ]
        );

        return $response;
    }

    private function extractIpAddress(string $ipAddress): string
    {
        $parts = explode(':', $ipAddress);
        if (count($parts) === 2 && filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $parts[0];
        }

        return $ipAddress;
    }
}
