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
