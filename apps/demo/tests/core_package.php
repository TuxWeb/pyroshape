<?php

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
