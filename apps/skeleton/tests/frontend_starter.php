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
    'frontend/components.json',
    'frontend/src/lib/utils.ts',
    'frontend/src/lib/components/ui/button/button.svelte',
    'frontend/src/lib/components/ui/input/input.svelte',
    'frontend/src/lib/components/ui/card/card.svelte',
    'frontend/src/lib/components/ui/label/label.svelte',
    'frontend/src/lib/components/ui/spinner/spinner.svelte',
    'frontend/src/lib/components/ui/alert/alert.svelte',
    'frontend/src/lib/api.ts',
    'frontend/src/lib/session.ts',
    'frontend/src/routes/account/password/+page.svelte',
    'docs/ai-delivery.md',
    'docs/frontend-style-guide.md',
];

foreach ($requiredFiles as $file) {
    assertFileExists($root . '/' . $file, $file);
}

$button = readProjectFile($root, 'frontend/src/lib/components/ui/Button.svelte');
assertContains($button, '{...props}', 'Button component forwards native attributes and callbacks');

$session = readProjectFile($root, 'frontend/src/lib/session.ts');
assertContains($session, 'ensureSessionLoaded', 'session helper exposes ensureSessionLoaded');
assertContains($session, 'sessionRequestId', 'session helper guards stale responses');

$layout = readProjectFile($root, 'frontend/src/routes/+layout.svelte');
assertContains($layout, 'AppShell', 'root layout uses reusable AppShell');

$appShell = readProjectFile($root, 'frontend/src/lib/components/layout/AppShell.svelte');
assertContains($appShell, 'href="/account/password"', 'app shell links authenticated users to password change');

$loginPage = readProjectFile($root, 'frontend/src/routes/login/+page.svelte');
assertContains($loginPage, 'ensureSessionLoaded', 'login page checks existing session');
assertContains($loginPage, 'current.authenticated', 'login page checks authenticated state');
assertContains($loginPage, "goto('/dashboard')", 'login page redirects authenticated users to dashboard');

$passwordPage = readProjectFile($root, 'frontend/src/routes/account/password/+page.svelte');
assertContains($passwordPage, '/api/auth/change-password', 'password page calls core change-password API');
assertContains($passwordPage, 'currentPassword', 'password page sends current password');
assertContains($passwordPage, 'newPassword', 'password page sends new password');
assertContains($passwordPage, 'confirmPassword', 'password page sends confirmation');
assertContains($passwordPage, 'ensureSessionLoaded', 'password page requires authenticated session');

$delivery = readProjectFile($root, 'docs/ai-delivery.md');
assertContains($delivery, 'vertical slice', 'AI delivery guide documents vertical slice workflow');
assertContains($delivery, 'Keep app domain code in the app', 'AI delivery guide protects core boundary');

$styleGuide = readProjectFile($root, 'docs/frontend-style-guide.md');
assertContains($styleGuide, 'Target app', 'frontend style guide documents target app adaptation');
assertContains($styleGuide, 'Reusable primitives', 'frontend style guide documents reusable primitives');

$projectSkill = readProjectFile($root, 'SKILL.md');
$retiredFlowbiteGuidance = 'Flowbite ' . 'M' . 'CP';
assertNotContains($projectSkill, $retiredFlowbiteGuidance, 'project guidance does not reference retired Flowbite integration');
assertNotContains($projectSkill, 'Ask ' . $retiredFlowbiteGuidance, 'project guidance does not instruct agents to use retired Flowbite integration');
assertContains($projectSkill, 'shadcn-svelte', 'project guidance documents shadcn-svelte');
assertContains($projectSkill, 'components.json', 'project guidance documents shadcn CLI configuration');

$package = json_decode(readProjectFile($root, 'frontend/package.json'), true);
if (! is_array($package)) {
    fwrite(STDERR, "Unable to decode frontend/package.json\n");
    exit(1);
}

$devDependencies = $package['devDependencies'] ?? [];
assertArrayHasKey($devDependencies, 'bits-ui', 'frontend package includes Bits UI');
assertArrayHasKey($devDependencies, 'tailwind-variants', 'frontend package includes Tailwind Variants');

foreach (array_keys($devDependencies) as $dependency) {
    assertNotContains($dependency, '@skeletonlabs/', 'frontend package excludes Skeleton UI');
}

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

function assertNotContains(string $haystack, string $needle, string $label): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Unexpected content: {$label}\n");
        exit(1);
    }
}

/**
 * @param array<string, mixed> $array
 */
function assertArrayHasKey(array $array, string $key, string $label): void
{
    if (!array_key_exists($key, $array)) {
        fwrite(STDERR, "Missing expected key: {$label}\n");
        exit(1);
    }
}
