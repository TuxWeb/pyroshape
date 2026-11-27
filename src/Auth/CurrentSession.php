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

use SensitiveParameter;

final readonly class CurrentSession
{
    public function __construct(
        public int $userId,
        public string $refreshJti,
        #[SensitiveParameter]
        private string $refreshTokenHash
    ) {
    }

    public function refreshTokenHash(): string
    {
        return $this->refreshTokenHash;
    }
}
