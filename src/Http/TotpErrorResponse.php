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

namespace TuxWeb\Pyroshape\Core\Http;

use Psr\Http\Message\ResponseInterface;
use TuxWeb\Pyroshape\Core\Auth\TotpException;

final class TotpErrorResponse
{
    public static function write(ResponseInterface $response, TotpException $error): ResponseInterface
    {
        $response->getBody()->write(json_encode([
            'error' => ['code' => $error->errorCode(), 'message' => $error->getMessage()],
        ], JSON_THROW_ON_ERROR));
        $response = $response->withStatus($error->statusCode())
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');

        $retryAfter = $error->retryAfter();

        return $retryAfter === null ? $response : $response->withHeader('Retry-After', (string) $retryAfter);
    }
}
