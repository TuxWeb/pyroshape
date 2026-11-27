<?php

/*
 * Copyright (c) 2026 TuxWeb
 * Author: Dino Ciuffetti <dino@tuxweb.it>, TuxWeb S.r.l. - https://www.tuxweb.it/
 * Date: 2026-11-27
 * SPDX-License-Identifier: MIT
 *
 * Permission is granted to use, modify, and distribute this software under
 * the MIT License. Keep the copyright and license notices with copies or
 * substantial portions. This software is provided as is, without warranty.
 * See LICENSE for the full terms.
 */

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TuxWeb\Pyroshape\Core\Auth\AuthService;
use TuxWeb\Pyroshape\Core\Auth\CurrentSession;
use TuxWeb\Pyroshape\Core\Auth\TotpException;
use TuxWeb\Pyroshape\Core\Http\TotpErrorResponse;
use TuxWeb\Pyroshape\Core\Middlewares\Auth\RequireCurrentSession;

final class TotpController
{
    public function __construct(private AuthService $auth)
    {
    }

    public function login(Request $request, Response $response): Response
    {
        return $this->respond($response, function () use ($request, $response): array {
            $input = $this->input($request);

            return $this->auth->loginTotp($request, $response, $this->field($input, 'flowId'), $this->code($input));
        });
    }

    public function status(Request $request, Response $response): Response
    {
        return $this->respond($response, fn (): array => $this->auth->totpStatus($this->session($request), $response));
    }

    public function setup(Request $request, Response $response): Response
    {
        return $this->respond($response, function () use ($request, $response): array {
            $input = $this->input($request);

            return $this->auth->startTotpSetup(
                $this->session($request),
                $response,
                $this->field($input, 'currentPassword'),
                array_key_exists('code', $input) ? $this->code($input) : null
            );
        });
    }

    public function confirmSetup(Request $request, Response $response): Response
    {
        return $this->respond($response, function () use ($request, $response): array {
            $input = $this->input($request);

            return $this->auth->confirmTotpSetup(
                $this->session($request),
                $response,
                $this->field($input, 'flowId'),
                $this->code($input)
            );
        });
    }

    public function disable(Request $request, Response $response): Response
    {
        return $this->respond($response, function () use ($request, $response): array {
            $input = $this->input($request);

            return $this->auth->disableTotp(
                $this->session($request),
                $response,
                $this->field($input, 'currentPassword'),
                $this->code($input)
            );
        });
    }

    public function recover(Request $request, Response $response): Response
    {
        return $this->respond($response, function () use ($request, $response): array {
            $input = $this->input($request);

            return $this->auth->startTotpRecovery(
                $request,
                $response,
                $this->field($input, 'flowId'),
                $this->field($input, 'recoveryCode')
            );
        });
    }

    public function confirmRecovery(Request $request, Response $response): Response
    {
        return $this->respond($response, function () use ($request, $response): array {
            $input = $this->input($request);

            return $this->auth->confirmTotpRecovery(
                $request,
                $response,
                $this->field($input, 'flowId'),
                $this->code($input)
            );
        });
    }

    private function respond(Response $response, callable $operation): Response
    {
        try {
            $payload = $operation();
        } catch (TotpException $error) {
            return TotpErrorResponse::write($response, $error);
        }

        $response = $payload['response'] ?? $response;
        unset($payload['response']);
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));
        $input = $request->getParsedBody();
        if ($contentType !== 'application/json' || !is_array($input) || array_is_list($input)) {
            throw new TotpException('invalid_request');
        }

        return $input;
    }

    /** @param array<string, mixed> $input */
    private function field(array $input, string $name): string
    {
        $value = $input[$name] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > 128) {
            throw new TotpException('invalid_request');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function code(array $input): string
    {
        $code = $this->field($input, 'code');
        if (!preg_match('/\A[0-9]{6}\z/', $code)) {
            throw new TotpException('invalid_request');
        }

        return $code;
    }

    private function session(Request $request): CurrentSession
    {
        return $request->getAttribute(RequireCurrentSession::SESSION_ATTRIBUTE)
            ?? throw new TotpException('current_session_required');
    }
}
