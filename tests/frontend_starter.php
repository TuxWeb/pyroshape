<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'frontend/src/lib/components/layout/AppShell.svelte',
    'frontend/src/lib/components/ui/Button.svelte',
    'frontend/src/lib/components/ui/Card.svelte',
    'frontend/src/lib/components/ui/EmptyState.svelte',
    'frontend/src/lib/components/ui/ErrorMessage.svelte',
    'frontend/src/lib/components/ui/FormField.svelte',
    'frontend/src/lib/components/ui/Input.svelte',
    'frontend/src/lib/components/ui/LoadingState.svelte',
    'frontend/src/lib/api.ts',
    'frontend/src/lib/session.ts',
    'docs/ai-delivery.md',
    'docs/frontend-style-guide.md',
];

foreach ($requiredFiles as $file) {
    assertFileExists($root . '/' . $file, $file);
}

$button = readProjectFile($root, 'frontend/src/lib/components/ui/Button.svelte');
assertContains($button, 'on:click', 'Button component forwards click events');

$session = readProjectFile($root, 'frontend/src/lib/session.ts');
assertContains($session, 'ensureSessionLoaded', 'session helper exposes ensureSessionLoaded');
assertContains($session, 'sessionRequestId', 'session helper guards stale responses');

$layout = readProjectFile($root, 'frontend/src/routes/+layout.svelte');
assertContains($layout, 'AppShell', 'root layout uses reusable AppShell');

$loginPage = readProjectFile($root, 'frontend/src/routes/login/+page.svelte');
assertContains($loginPage, 'ensureSessionLoaded', 'login page checks existing session');
assertContains($loginPage, 'current.authenticated', 'login page checks authenticated state');
assertContains($loginPage, "goto('/dashboard')", 'login page redirects authenticated users to dashboard');

$delivery = readProjectFile($root, 'docs/ai-delivery.md');
assertContains($delivery, 'vertical slice', 'AI delivery guide documents vertical slice workflow');
assertContains($delivery, 'Do not add app domain code to pyroshape-core', 'AI delivery guide protects core boundary');

$styleGuide = readProjectFile($root, 'docs/frontend-style-guide.md');
assertContains($styleGuide, 'Target app', 'frontend style guide documents target app adaptation');
assertContains($styleGuide, 'Reusable primitives', 'frontend style guide documents reusable primitives');

echo "frontend starter ok\n";

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
