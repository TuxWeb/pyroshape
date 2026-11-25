<?php

declare(strict_types=1);

use Dam2k\Pyroshape\Core\Auth\Tokens\AccessToken;
use Dam2k\Pyroshape\Core\Auth\Tokens\TokenHandler;
use Dam2k\Pyroshape\Core\Http\EncryptedCookies;
use DI\Container;
use Firebase\JWT\JWT;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$container = new Container([
    'settings' => [
        'jwt_refresh_token' => [
            'key' => hash('sha256', 'refresh-token-key'),
            'algorithm' => 'HS256',
            'iss' => 'pyroshape-test',
            'time_skew' => 0,
            'expire' => 2,
        ],
        'jwt_access_token' => [
            'secret' => hash('sha256', 'access-token-key'),
            'algorithm' => 'HS256',
            'iss' => 'pyroshape-test',
            'time_skew' => 0,
            'ttl' => 60,
        ],
    ],
]);

$handler = new TokenHandler($container);
$handler->generateRefreshToken('user-123', ['scope' => 'test']);
$refreshToken = $handler->getRefreshToken();
$refreshClaims = $refreshToken->getToken();
$accessToken = $refreshToken->getAccessToken();
$accessClaims = $accessToken->getToken();

if ($refreshClaims->aud !== 'RT' || $accessClaims->aud !== 'AT') {
    fail('Unexpected token audience');
}

if ($accessClaims->sub !== 'user-123' || $accessClaims->scope !== 'test') {
    fail('Access token did not preserve refresh token subject and custom claims');
}

if ($accessClaims->exp > $refreshClaims->exp) {
    fail('Access token expiration was not capped by refresh token expiration');
}

$handler->loadRefreshTokenFromJWT($refreshToken->getTokenJWT());
if ($handler->getRefreshToken()->getToken()->sub !== 'user-123') {
    fail('Refresh token failed to reload from JWT');
}

$handler->loadAccessTokenFromJWT($accessToken->getTokenJWT());
if ($handler->getAccessToken()->getToken()->sub !== 'user-123') {
    fail('Access token failed to reload from JWT');
}

$wrongAudienceRejected = false;
try {
    (new AccessToken($container))->loadAccessTokenFromJWT($refreshToken->getTokenJWT());
} catch (Throwable) {
    $wrongAudienceRejected = true;
}

if (!$wrongAudienceRejected) {
    fail('Refresh token was accepted as an access token');
}

$reservedClaimRejected = false;
try {
    $handler->generateRefreshToken('user-123', ['aud' => 'AT']);
} catch (InvalidArgumentException) {
    $reservedClaimRejected = true;
}

if (!$reservedClaimRejected) {
    fail('Reserved JWT claim override was accepted');
}

$wrongIssuerJwt = JWT::encode([
    'iss' => 'wrong-issuer',
    'aud' => 'AT',
    'sub' => 'user-123',
    'iat' => time(),
    'nbf' => time(),
    'exp' => time() + 60,
    'jti' => 'wrong-issuer-test',
], hash('sha256', 'access-token-key'), 'HS256');

$wrongIssuerRejected = false;
try {
    $handler->loadAccessTokenFromJWT($wrongIssuerJwt);
} catch (Throwable) {
    $wrongIssuerRejected = true;
}

if (!$wrongIssuerRejected) {
    fail('Wrong issuer JWT was accepted');
}

$cookieKey = hash('sha256', 'cookie-key');
$cookieSalt = 'cookie-salt';
$response = (new ResponseFactory())->createResponse();
$response = EncryptedCookies::setEncryptedCookie(
    $response,
    'session',
    $refreshToken->getTokenJWT(),
    $cookieKey,
    $cookieSalt,
    ['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']
);

$headers = $response->getHeader('Set-Cookie');
if (count($headers) !== 1) {
    fail('Expected one Set-Cookie header');
}

if (str_contains($headers[0], $refreshToken->getTokenJWT())) {
    fail('Encrypted cookie exposed the JWT value');
}

if (!str_contains($headers[0], 'HttpOnly') || !str_contains($headers[0], 'SameSite=Lax')) {
    fail('Encrypted cookie did not include expected security attributes');
}

$cookiePair = explode(';', $headers[0], 2)[0];
[$cookieName, $cookieValue] = explode('=', $cookiePair, 2);
$request = (new ServerRequestFactory())->createServerRequest('GET', '/')
    ->withCookieParams([$cookieName => urldecode($cookieValue)]);

if (EncryptedCookies::getRequestCookieValue($request, 'session') === $refreshToken->getTokenJWT()) {
    fail('Request cookie value was not encrypted');
}

$decrypted = EncryptedCookies::decryptCookie($request, 'session', $cookieKey, $cookieSalt);
if ($decrypted !== $refreshToken->getTokenJWT()) {
    fail('Encrypted cookie failed to decrypt');
}

$nonAeadRejected = false;
try {
    EncryptedCookies::setEncryptedCookie(
        (new ResponseFactory())->createResponse(),
        'session',
        $refreshToken->getTokenJWT(),
        $cookieKey,
        $cookieSalt,
        ['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax'],
        'aes-128-cbc'
    );
} catch (InvalidArgumentException) {
    $nonAeadRejected = true;
}

if (!$nonAeadRejected) {
    fail('Non-AEAD cipher was accepted');
}

$clearedResponse = EncryptedCookies::unsetEncryptedCookie(
    (new ResponseFactory())->createResponse(),
    'session',
    ['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']
);

if (!str_contains($clearedResponse->getHeaderLine('Set-Cookie'), 'expires=Thu, 01 Jan 1970')) {
    fail('Unset cookie did not expire in the past');
}

echo "token cookie ok\n";
