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

$check = $argc === 2 && $argv[1] === '--check';
if ($argc !== 1 && !$check) {
    fwrite(STDERR, "Usage: php scripts/add-php-headers.php [--check]\n");
    exit(2);
}

$root = dirname(__DIR__);
$process = proc_open(
    ['git', 'ls-files', '--cached', '-z', '--', '*.php', ':(exclude,glob)**/vendor/**'],
    [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR],
    $pipes,
    $root,
);
if ($process === false) {
    exit(1);
}

$listing = stream_get_contents($pipes[1]);
fclose($pipes[1]);
if (proc_close($process) !== 0 || $listing === false) {
    exit(1);
}

$files = array_unique(explode("\0", $listing));
$copyright = 'Copyright (c) 2026 TuxWeb';
$author = 'Author: Dino Ciuffetti <dino@tuxweb.it>, TuxWeb S.r.l. - https://www.tuxweb.it/';
$date = gmdate('Y-m-d');
$header = [
    '/*',
    ' * ' . $copyright,
    ' * ' . $author,
    ' * Date: ' . $date,
    ' * SPDX-License-Identifier: MIT',
    ' *',
    ' * Permission is granted to use, modify, and distribute this software under',
    ' * the MIT License. Keep the copyright and license notices with copies or',
    ' * substantial portions. This software is provided as is, without warranty.',
    ' * See LICENSE for the full terms.',
    ' */',
];

$pending = 0;
$failed = false;
foreach ($files as $file) {
    if ($file === '') {
        continue;
    }

    try {
        $path = $root . '/' . $file;
        if (is_link($path) || !is_file($path)) {
            throw new RuntimeException('Expected a regular file');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Could not read file');
        }

        $offset = phpOpeningTagEnd($contents);
        $before = substr($contents, 0, $offset);
        $after = substr($contents, $offset);
        $lineEnding = preg_match('/\r?\n/', $contents, $match) === 1 ? $match[0] : "\n";
        $signature = '/*' . $lineEnding . ' * ' . $copyright . $lineEnding . ' * ' . $author;
        if (str_starts_with(ltrim($after), $signature)) {
            continue;
        }

        $pending++;
        if ($check) {
            echo "Missing header: $file\n";
            continue;
        }

        $separator = str_ends_with($before, $lineEnding) ? $lineEnding : $lineEnding . $lineEnding;
        replaceFile($path, $before . $separator . implode($lineEnding, $header) . $lineEnding . $after);
    } catch (RuntimeException $exception) {
        $failed = true;
        fwrite(STDERR, "$file: {$exception->getMessage()}\n");
    }
}

if ($check) {
    echo "PHP files missing headers: $pending\n";
} elseif (!$failed) {
    echo "PHP headers added: $pending\n";
}
exit($failed || ($check && $pending > 0) ? 1 : 0);

function phpOpeningTagEnd(string $contents): int
{
    $offset = 0;
    foreach (token_get_all($contents) as $token) {
        $offset += strlen(is_array($token) ? $token[1] : $token);
        if (is_array($token) && $token[0] === T_OPEN_TAG) {
            return $offset;
        }
    }

    throw new RuntimeException('No PHP opening tag found');
}

function replaceFile(string $path, string $contents): void
{
    $mode = fileperms($path);
    $temporary = tempnam(dirname($path), '.php-header-');
    if ($temporary === false) {
        throw new RuntimeException('Could not create a temporary file');
    }

    try {
        if ($mode === false || file_put_contents($temporary, $contents) !== strlen($contents)
            || !chmod($temporary, $mode & 0777) || !rename($temporary, $path)) {
            throw new RuntimeException('Could not replace file');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}
