#!/usr/bin/env php
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

use TuxWeb\Pyroshape\Core\App;
use TuxWeb\Pyroshape\Core\Auth\UserRepository;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

if ($argc < 2 || $argc > 3 || (isset($argv[2]) && !preg_match('/\A[1-9][0-9]{0,3}\z/', $argv[2]))) {
    fwrite(STDERR, "Usage: php pyroshape-prune-totp.php APP_CONFIG [BATCH_SIZE]\n");
    exit(2);
}

require $_composer_autoload_path ?? dirname(__DIR__) . '/vendor/autoload.php';

try {
    $container = (new App($argv[1]))->getSlimApp()->getContainer();
    $repository = $container->get(UserRepository::class)->totpRepository();
    $deleted = $repository->deleteExpiredFlows(gmdate('Y-m-d H:i:s'), isset($argv[2]) ? (int) $argv[2] : 100);
    fwrite(STDOUT, "Removed $deleted expired TOTP flows.\n");
} catch (Throwable) {
    fwrite(STDERR, "TOTP cleanup failed. Check the app configuration, database connection, and TOTP migration.\n");
    exit(1);
}
