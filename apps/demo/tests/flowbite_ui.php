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

$root = dirname(__DIR__);

$requiredFiles = [
    'frontend/src/lib/components/ui/Alert.svelte',
    'frontend/src/lib/components/ui/Badge.svelte',
    'frontend/src/lib/components/ui/Modal.svelte',
    'frontend/src/lib/components/ui/Spinner.svelte',
    'frontend/src/lib/components/ui/Table.svelte',
    'frontend/src/lib/components/ui/Textarea.svelte',
];

foreach ($requiredFiles as $file) {
    assertFileExists($root . '/' . $file, $file);
}

$button = readProjectFile($root, 'frontend/src/lib/components/ui/Button.svelte');
assertContains($button, 'rounded-lg', 'Button uses Flowbite-style rounded-lg controls');
assertContains($button, 'focus:ring-4', 'Button uses Flowbite-style focus ring');

$shell = readProjectFile($root, 'frontend/src/lib/components/layout/AppShell.svelte');
assertNotContains($shell, '<aside', 'AppShell does not render a second sidebar navigation');
assertNotContains($shell, '/dashboard', 'AppShell does not link to removed dashboard route');
assertContains($shell, 'Pyroshape Demo', 'AppShell keeps demo branding');
assertContains($shell, '{#if $session.authenticated}', 'AppShell gates protected navigation by authenticated session');
assertOrdered($shell, '{#if $session.authenticated}', '{#each navItems as item}', 'Protected nav items render only inside authenticated branch');

$home = readProjectFile($root, 'frontend/src/routes/+page.svelte');
assertContains($home, "'/projects'", 'Authenticated home redirect goes to projects');
assertNotContains($home, '/dashboard', 'Home redirect does not target dashboard');

$login = readProjectFile($root, 'frontend/src/routes/login/+page.svelte');
assertContains($login, "goto('/projects')", 'Login redirects authenticated users to projects');
assertNotContains($login, '/dashboard', 'Login redirect does not target dashboard');

$projects = readProjectFile($root, 'frontend/src/routes/projects/+page.svelte');
assertContains($projects, 'Table', 'Projects page uses reusable Table');
assertContains($projects, 'Modal', 'Projects page uses confirmation modal');
assertContains($projects, 'Badge', 'Projects page uses badges');

$detail = readProjectFile($root, 'frontend/src/routes/projects/[id]/+page.svelte');
assertContains($detail, 'Badge', 'Project detail uses badges');
assertContains($detail, 'Modal', 'Project detail uses confirmation modal');
assertNotContains($detail, 'window.confirm', 'Project detail avoids browser confirm');

echo "flowbite ui ok\n";

function readProjectFile(string $root, string $file): string
{
    $path = $root . '/' . $file;
    assertFileExists($path, $file);

    return (string) file_get_contents($path);
}

function assertFileExists(string $path, string $label): void
{
    if (!is_file($path)) {
        fwrite(STDERR, "Missing expected file: {$label}\n");
        exit(1);
    }
}

function assertContains(string $haystack, string $needle, string $label): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing expected content: {$label}\n");
        exit(1);
    }
}

function assertNotContains(string $haystack, string $needle, string $label): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Unexpected content: {$label}\n");
        exit(1);
    }
}

function assertOrdered(string $haystack, string $first, string $second, string $label): void
{
    $firstPosition = strpos($haystack, $first);
    $secondPosition = strpos($haystack, $second);

    if ($firstPosition === false || $secondPosition === false || $firstPosition > $secondPosition) {
        fwrite(STDERR, "Unexpected order: {$label}\n");
        exit(1);
    }
}
