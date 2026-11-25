<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists(\TuxWeb\Pyroshape\Core\App::class)) {
    fwrite(STDERR, "Core App class not autoloadable\n");
    exit(1);
}

$packageName = 'tuxweb/pyroshape';
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
