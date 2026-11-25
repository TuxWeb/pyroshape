<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists(\Dam2k\Pyroshape\Core\App::class)) {
    fwrite(STDERR, "Core App class not autoloadable\n");
    exit(1);
}

$coreAppFile = (new ReflectionClass(\Dam2k\Pyroshape\Core\App::class))->getFileName();
$expectedPrefix = realpath(__DIR__ . '/../../../../pyroshape-core');

if ($expectedPrefix === false || !str_starts_with((string) $coreAppFile, $expectedPrefix . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Core package not loaded from /home/dino/projects/pyroshape-core\n");
    exit(1);
}

echo "core package ok\n";
