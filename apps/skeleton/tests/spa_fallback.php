<?php

declare(strict_types=1);

use TuxWeb\PyroshapeSkeleton\App;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';

$publicAppDir = __DIR__ . '/../public/app';
$indexFile = $publicAppDir . '/index.html';
if (!is_file($indexFile)) {
    fwrite(STDERR, "Missing built SPA index at $indexFile\n");
    exit(1);
}

$slim = (new App(__DIR__ . '/../config/config.php'))->getSlimApp();
$requestFactory = new ServerRequestFactory();

$frontendResponse = $slim->handle($requestFactory->createServerRequest('GET', '/login'));
$frontendBody = (string) $frontendResponse->getBody();

if ($frontendResponse->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected frontend status 200, got {$frontendResponse->getStatusCode()}\n");
    fwrite(STDERR, "Response body: $frontendBody\n");
    exit(1);
}

if (!str_contains($frontendResponse->getHeaderLine('Content-Type'), 'text/html')) {
    fwrite(STDERR, "Expected frontend Content-Type text/html, got {$frontendResponse->getHeaderLine('Content-Type')}\n");
    exit(1);
}

if (!str_contains($frontendBody, '<script') || !str_contains($frontendBody, '/_app/')) {
    fwrite(STDERR, "Frontend fallback did not serve public/app/index.html\n");
    fwrite(STDERR, "Response body: $frontendBody\n");
    exit(1);
}

$assetPath = firstBuiltJsAssetPath($frontendBody);
$assetResponse = $slim->handle($requestFactory->createServerRequest('GET', $assetPath));
$assetBody = (string) $assetResponse->getBody();

if ($assetResponse->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected built JS asset $assetPath status 200, got {$assetResponse->getStatusCode()}\n");
    fwrite(STDERR, "Response body: $assetBody\n");
    exit(1);
}

if (str_contains($assetResponse->getHeaderLine('Content-Type'), 'text/html') || str_starts_with(ltrim($assetBody), '<!doctype html')) {
    fwrite(STDERR, "Expected built JS asset $assetPath to return JS/non-HTML content\n");
    fwrite(STDERR, "Content-Type: {$assetResponse->getHeaderLine('Content-Type')}\n");
    fwrite(STDERR, "Response body: $assetBody\n");
    exit(1);
}

$apiResponse = $slim->handle($requestFactory->createServerRequest('GET', '/api/not-found'));

if ($apiResponse->getStatusCode() !== 404) {
    fwrite(STDERR, "Expected /api/not-found status 404, got {$apiResponse->getStatusCode()}\n");
    fwrite(STDERR, "Response body: " . (string) $apiResponse->getBody() . "\n");
    exit(1);
}

$healthResponse = $slim->handle($requestFactory->createServerRequest('GET', '/health/not-found'));
$healthBody = (string) $healthResponse->getBody();

if ($healthResponse->getStatusCode() === 200 && str_contains($healthResponse->getHeaderLine('Content-Type'), 'text/html')) {
    fwrite(STDERR, "/health/not-found was captured by the SPA fallback\n");
    fwrite(STDERR, "Response body: $healthBody\n");
    exit(1);
}

echo "spa fallback ok\n";

function firstBuiltJsAssetPath(string $html): string
{
    if (!preg_match('#/(?:app/)?_app/immutable/[^"\']+\.js#', $html, $matches)) {
        fwrite(STDERR, "Could not find built JavaScript asset in index.html\n");
        fwrite(STDERR, "Response body: $html\n");
        exit(1);
    }

    return $matches[0];
}
