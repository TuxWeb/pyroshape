<?php

declare(strict_types=1);

namespace Dam2k\Pyroshape\Core\Controllers;

use Dam2k\Pyroshape\Core\Auth\AuthService;
use Dam2k\Pyroshape\Core\Auth\AuthException;
use InvalidArgumentException;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class AuthController
{
    public function __construct(private AuthService $auth)
    {
    }

    public function postRegister(Request $request, Response $response): Response
    {
        try {
            $body = $this->requestBody($request);
            $payload = $this->auth->registerLocalUser(
                $this->stringField($body, 'email'),
                $this->stringField($body, 'password'),
                [
                    'nickname' => $body['nickname'] ?? null,
                    'name' => $body['name'] ?? null,
                    'surname' => $body['surname'] ?? null,
                ]
            );

            return $this->json($response, $payload, 201);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 400);
        } catch (PDOException $exception) {
            if ($this->isDuplicateEmailError($exception)) {
                return $this->jsonError($response, 'Email address is already registered', 409);
            }

            throw $exception;
        }
    }

    public function getVerifyEmail(Request $request, Response $response): Response
    {
        try {
            $token = $request->getQueryParams()['token'] ?? '';
            $payload = $this->auth->verifyEmailToken(is_string($token) ? $token : '');

            return $this->json($response, $payload);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 400);
        }
    }

    public function postLogin(Request $request, Response $response): Response
    {
        try {
            $body = $this->requestBody($request);
            $payload = $this->auth->login(
                $this->stringField($body, 'email'),
                $this->stringField($body, 'password'),
                $response
            );
            $response = $this->payloadResponse($payload, $response);

            return $this->json($response, $this->withoutResponse($payload));
        } catch (AuthException $exception) {
            $message = $exception->statusCode() === 401
                ? 'Invalid email or password'
                : 'Authentication is not available for this account';

            return $this->jsonError($response, $message, $exception->statusCode());
        } catch (InvalidArgumentException) {
            return $this->jsonError($response, 'Invalid email or password', 401);
        }
    }

    public function postLogout(Request $request, Response $response): Response
    {
        if (!$this->hasValidSameOriginHeader($request)) {
            return $this->jsonError($response, 'Forbidden', 403);
        }

        $payload = $this->auth->logout($request, $response);
        $response = $this->payloadResponse($payload, $response);

        return $this->json($response, ['authenticated' => false]);
    }

    public function getSession(Request $request, Response $response): Response
    {
        $payload = $this->auth->getSession($request, $response);
        $response = $this->payloadResponse($payload, $response);

        return $this->json($response, $this->withoutResponse($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }

        $rawBody = trim((string) $request->getBody());
        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function stringField(array $body, string $name): string
    {
        $value = $body[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadResponse(array $payload, Response $fallback): Response
    {
        $response = $payload['response'] ?? $fallback;

        return $response instanceof Response ? $response : $fallback;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withoutResponse(array $payload): array
    {
        unset($payload['response']);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        return $this->json($response, ['error' => ['message' => $message]], $status);
    }

    private function hasValidSameOriginHeader(Request $request): bool
    {
        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin === '') {
            return true;
        }

        $origin = $this->normalizeOrigin($origin);
        $requestOrigin = $this->requestOrigin($request);
        if ($origin === null || $requestOrigin === null) {
            return false;
        }

        return $origin === $requestOrigin;
    }

    private function requestOrigin(Request $request): ?string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        if ($scheme === '') {
            $scheme = 'http';
        }
        if ($host === '') {
            return $this->normalizeOrigin($scheme . '://' . $request->getHeaderLine('Host'));
        }

        return $this->originFromParts($scheme, $host, $uri->getPort());
    }

    private function normalizeOrigin(string $origin): ?string
    {
        if ($origin === '') {
            return null;
        }

        $parts = parse_url($origin);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '') {
            return null;
        }

        $port = $parts['port'] ?? null;

        return $this->originFromParts($scheme, $host, is_int($port) ? $port : null);
    }

    private function originFromParts(string $scheme, string $host, ?int $port): ?string
    {
        $scheme = strtolower($scheme);
        $host = strtolower($host);
        if ($scheme === '' || $host === '') {
            return null;
        }

        $origin = $scheme . '://' . $host;
        if ($port !== null && !$this->isDefaultPort($scheme, $port)) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }

    private function isDuplicateEmailError(PDOException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = isset($exception->errorInfo[1]) ? (string) $exception->errorInfo[1] : '';
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000'
            || $driverCode === '1062'
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
