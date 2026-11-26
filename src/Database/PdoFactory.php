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

namespace TuxWeb\Pyroshape\Core\Database;

use PDO;
use Psr\Container\ContainerInterface;

class PdoFactory
{
    public function __invoke(ContainerInterface $container): PDO
    {
        $settings = $container->get('settings');
        $database = $settings['database'] ?? [];

        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $options = $database['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }

        return new PDO(
            (string) ($database['dsn'] ?? ''),
            $database['username'] ?? null,
            $database['password'] ?? null,
            $options + $defaults
        );
    }
}
