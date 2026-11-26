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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class RemoveTrailingSlash extends GenericMiddleware implements MiddlewareInterface
{
    public function __construct(private ContainerInterface $container, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $basePath = $this->container->get('settings')['basePath'];

        if ($path !== $basePath . '/' && str_ends_with($path, '/')) {
            $newUri = $uri->withPath(rtrim($path, '/'));

            if ($request->getMethod() === 'GET') {
                $this->logger->info("Redirecting without trailing slash: $uri => $newUri");

                return (new ResponseFactory())
                    ->createResponse()
                    ->withHeader('Location', (string) $newUri)
                    ->withStatus(301);
            }

            $request = $request->withUri($newUri);
        }

        return $handler->handle($request);
    }
}
