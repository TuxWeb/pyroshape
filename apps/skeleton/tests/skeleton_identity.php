<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
if (($composer['name'] ?? null) !== 'tuxweb/pyroshape-skeleton') {
    fwrite(STDERR, "Composer package is not tuxweb/pyroshape-skeleton\n");
    exit(1);
}

if (!isset($composer['autoload']['psr-4']['TuxWeb\\PyroshapeSkeleton\\'])) {
    fwrite(STDERR, "Composer autoload namespace TuxWeb\\PyroshapeSkeleton\\ missing\n");
    exit(1);
}

$frontend = json_decode((string) file_get_contents($root . '/frontend/package.json'), true, flags: JSON_THROW_ON_ERROR);
if (($frontend['name'] ?? null) !== 'pyroshape-skeleton-frontend') {
    fwrite(STDERR, "Frontend package is not pyroshape-skeleton-frontend\n");
    exit(1);
}

require $root . '/vendor/autoload.php';

if (!class_exists(\TuxWeb\PyroshapeSkeleton\App::class)) {
    fwrite(STDERR, "TuxWeb\\PyroshapeSkeleton\\App is not autoloadable\n");
    exit(1);
}

echo "skeleton identity ok\n";
