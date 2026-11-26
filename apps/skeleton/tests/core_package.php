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

$packageName = 'tuxweb-it/pyroshape';
if (!\Composer\InstalledVersions::isInstalled($packageName)) {
    fwrite(STDERR, "Core package is not installed by Composer\n");
    exit(1);
}

$installPath = \Composer\InstalledVersions::getInstallPath($packageName);
$expectedPrefix = $installPath === null ? false : realpath($installPath);
$coreAppFile = (new ReflectionClass(\TuxWeb\Pyroshape\Core\App::class))->getFileName();
$resolvedCoreAppFile = $coreAppFile === false ? false : realpath($coreAppFile);

if (
    $expectedPrefix === false
    || $resolvedCoreAppFile === false
    || !str_starts_with($resolvedCoreAppFile, $expectedPrefix . DIRECTORY_SEPARATOR)
) {
    fwrite(STDERR, "Core App class is not loaded from the Composer installation of $packageName\n");
    exit(1);
}

echo "core package ok\n";
