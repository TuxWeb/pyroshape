<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Auth\Tokens;

use RuntimeException;
use stdClass;

class RefreshToken extends GenericToken
{
    private const TOKEN_AUD = 'RT';
    private const SETTINGS_NAME = 'jwt_refresh_token';

    /**
     * @param array<string, mixed> $other
     */
    public function generateRefreshToken(string $sub = '', array $other = []): void
    {
        $settings = $this->settings(self::SETTINGS_NAME);
        $now = time();

        $this->jwt = $this->encodeJWT(
            $this->tokenKey($settings, self::SETTINGS_NAME),
            $this->tokenAlgorithm($settings, self::SETTINGS_NAME),
            $this->tokenIssuer($settings),
            self::TOKEN_AUD,
            $sub,
            $now,
            $now - $this->tokenTimeSkew($settings),
            $now + $this->tokenExpire($settings, self::SETTINGS_NAME) + $this->tokenTimeSkew($settings),
            $other
        );
    }

    public function loadRefreshTokenFromJWT(string $jwt): void
    {
        $settings = $this->settings(self::SETTINGS_NAME);
        $token = $this->decodeJWT(
            $jwt,
            $this->tokenKey($settings, self::SETTINGS_NAME),
            $this->tokenAlgorithm($settings, self::SETTINGS_NAME)
        );

        try {
            $this->assertExpectedToken($token, self::TOKEN_AUD, $this->tokenIssuer($settings));
        } catch (RuntimeException) {
            throw new RuntimeException('Cannot load refresh token from JWT: token invalid or not a refresh token');
        }

        $this->jwt = $jwt;
    }

    public function getToken(): stdClass
    {
        $this->assertPrepared();
        $settings = $this->settings(self::SETTINGS_NAME);

        $token = $this->decodeJWT(
            $this->jwt,
            $this->tokenKey($settings, self::SETTINGS_NAME),
            $this->tokenAlgorithm($settings, self::SETTINGS_NAME)
        );
        $this->assertExpectedToken($token, self::TOKEN_AUD, $this->tokenIssuer($settings));

        return $token;
    }

    public function getTokenJWT(): string
    {
        $this->assertPrepared();

        return $this->jwt;
    }

    public function getAccessToken(): AccessToken
    {
        $this->assertPrepared();

        $refreshClaims = $this->getToken();
        $accessToken = new AccessToken($this->container);
        $accessExpiration = time() + $accessToken->maxExpiration();
        if ((int) $refreshClaims->exp < $accessExpiration) {
            $accessExpiration = (int) $refreshClaims->exp;
        }

        $reservedClaims = array_flip(['iss', 'aud', 'sub', 'iat', 'nbf', 'exp', 'jti']);
        $other = array_diff_key((array) $refreshClaims, $reservedClaims);
        $accessToken->generateAccessToken((string) $refreshClaims->sub, $accessExpiration, $other);

        return $accessToken;
    }

    private function assertPrepared(): void
    {
        if ($this->jwt === null || $this->jwt === '') {
            throw new RuntimeException(
                'Cannot get refresh token object. Please prepare the token first with generateRefreshToken() or loadRefreshTokenFromJWT()'
            );
        }
    }
}
