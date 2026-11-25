<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
if (($composer['name'] ?? null) !== 'tuxweb/pyroshape-demo') {
    fwrite(STDERR, "Composer package is not tuxweb/pyroshape-demo\n");
    exit(1);
}

if (!isset($composer['autoload']['psr-4']['TuxWeb\\PyroshapeDemo\\'])) {
    fwrite(STDERR, "Composer autoload namespace TuxWeb\\PyroshapeDemo\\ missing\n");
    exit(1);
}

$frontend = json_decode((string) file_get_contents($root . '/frontend/package.json'), true, flags: JSON_THROW_ON_ERROR);
if (($frontend['name'] ?? null) !== 'pyroshape-demo-frontend') {
    fwrite(STDERR, "Frontend package is not pyroshape-demo-frontend\n");
    exit(1);
}

require $root . '/vendor/autoload.php';

if (!class_exists(\TuxWeb\PyroshapeDemo\App::class)) {
    fwrite(STDERR, "TuxWeb\\PyroshapeDemo\\App is not autoloadable\n");
    exit(1);
}

echo "demo identity ok\n";
