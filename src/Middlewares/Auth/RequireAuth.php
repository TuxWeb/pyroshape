<?php

/*
 * Copyright (c) 2026 TuxWeb
 * Author: Dino Ciuffetti <dino@tuxweb.it>, TuxWeb S.r.l. - https://www.tuxweb.it/
 * Date: 2026-11-26
 * SPDX-License-Identifier: MIT
 *
 * Permission is granted to use, modify, and distribute this software under
 * the MIT License. Keep the copyright and license notices with copies or
 * substantial portions. This software is provided as is, without warranty.
 * See LICENSE for the full terms.
 */

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Middlewares\Auth;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class RequireAuth implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $uid = $request->getAttribute(CheckValidAuthToken::UID_ATTRIBUTE, '');
        if (!is_string($uid) || $uid === '') {
            return $this->unauthorized();
        }

        return $handler->handle($request);
    }

    private function unauthorized(): Response
    {
        $response = new \Slim\Psr7\Response(401);
        $response->getBody()->write(json_encode(['error' => ['message' => 'Unauthorized']], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
