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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TuxWeb\Pyroshape\Core\Auth\TotpException;
use TuxWeb\Pyroshape\Core\Http\TotpErrorResponse;

final class RequireSameOrigin implements MiddlewareInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ResponseFactoryInterface $responses
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->allows($request)) {
            return TotpErrorResponse::write($this->responses->createResponse(), new TotpException('origin_rejected'));
        }

        return $handler->handle($request);
    }

    public function allows(ServerRequestInterface $request): bool
    {
        $settings = $this->container->get('settings');
        $origin = $settings['auth']['totp']['publicOrigin'] ?? null;

        return ($origin === null || is_string($origin)) && self::matches($request, $origin);
    }

    public static function matches(ServerRequestInterface $request, ?string $publicOrigin = null): bool
    {
        $origin = self::normalize($request->getHeaderLine('Origin'));
        $uri = $request->getUri();
        $target = $publicOrigin ?? $uri->getScheme() . '://' . $uri->getAuthority();

        return $origin !== null && $origin === self::normalize($target);
    }

    private static function normalize(string $origin): ?string
    {
        if (preg_match('~[\s,]~', $origin)) {
            return null;
        }

        $parts = parse_url($origin);
        if (!is_array($parts) || array_diff_key($parts, array_flip(['scheme', 'host', 'port']))) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $scheme . '://' . $host . ':' . $port;
    }
}
