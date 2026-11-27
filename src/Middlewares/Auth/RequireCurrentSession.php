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

namespace TuxWeb\Pyroshape\Core\Middlewares\Auth;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TuxWeb\Pyroshape\Core\Auth\AuthService;
use TuxWeb\Pyroshape\Core\Auth\TotpException;
use TuxWeb\Pyroshape\Core\Http\TotpErrorResponse;

final class RequireCurrentSession implements MiddlewareInterface
{
    public const string SESSION_ATTRIBUTE = 'pyroshape.current_session';

    public function __construct(private AuthService $auth, private ResponseFactoryInterface $responses)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $session = $this->auth->currentSession($request);
        } catch (TotpException $error) {
            return TotpErrorResponse::write($this->responses->createResponse(), $error);
        }

        return $handler->handle($request->withAttribute(self::SESSION_ATTRIBUTE, $session));
    }
}
