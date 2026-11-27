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

namespace TuxWeb\Pyroshape\Core\Auth;

final class TotpException extends AuthException
{
    private const ERRORS = [
        'invalid_request' => [400, 'Invalid authentication request'],
        'invalid_code' => [401, 'Invalid authentication code'],
        'invalid_credentials' => [401, 'Current password is incorrect'],
        'current_session_required' => [401, 'A current session is required'],
        'origin_rejected' => [403, 'Request origin is not allowed'],
        'flow_unavailable' => [409, 'Authentication flow is no longer available'],
        'enrollment_unavailable' => [409, 'Authenticator enrollment is unavailable'],
        'rate_limited' => [429, 'Too many authentication attempts'],
        'auth_unavailable' => [503, 'Authentication is temporarily unavailable'],
    ];

    public function __construct(private string $errorCode, private ?int $retryAfter = null)
    {
        [$status, $message] = self::ERRORS[$errorCode];

        parent::__construct($message, $status);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
