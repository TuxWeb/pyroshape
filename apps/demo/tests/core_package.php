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

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists(\TuxWeb\Pyroshape\Core\App::class)) {
    fwrite(STDERR, "Core App class not autoloadable\n");
    exit(1);
}

$coreAppFile = (new ReflectionClass(\TuxWeb\Pyroshape\Core\App::class))->getFileName();
$expectedFile = realpath(dirname(__DIR__, 3) . '/src/App.php');

if ($expectedFile === false || $coreAppFile !== $expectedFile) {
    fwrite(STDERR, "Core package not loaded from the monorepo root\n");
    exit(1);
}

echo "core package ok\n";
