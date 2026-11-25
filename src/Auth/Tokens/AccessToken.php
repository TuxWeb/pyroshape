<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Auth\Tokens;

use RuntimeException;
use stdClass;

class AccessToken extends GenericToken
{
    private const TOKEN_AUD = 'AT';
    private const SETTINGS_NAME = 'jwt_access_token';

    public function maxExpiration(): int
    {
        $settings = $this->settings(self::SETTINGS_NAME);

        return $this->tokenExpire($settings, self::SETTINGS_NAME) + $this->tokenTimeSkew($settings);
    }

    /**
     * @param array<string, mixed> $other
     */
    public function generateAccessToken(string $sub = '', int $expiration = 0, array $other = []): void
    {
        $settings = $this->settings(self::SETTINGS_NAME);
        $now = time();
        if ($expiration === 0) {
            $expiration = $now + $this->maxExpiration();
        }

        $this->jwt = $this->encodeJWT(
            $this->tokenKey($settings, self::SETTINGS_NAME),
            $this->tokenAlgorithm($settings, self::SETTINGS_NAME),
            $this->tokenIssuer($settings),
            self::TOKEN_AUD,
            $sub,
            $now,
            $now - $this->tokenTimeSkew($settings),
            $expiration,
            $other
        );
    }

    public function loadAccessTokenFromJWT(string $jwt): void
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
            throw new RuntimeException('Cannot load access token from JWT: token invalid or not an access token');
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

    private function assertPrepared(): void
    {
        if ($this->jwt === null || $this->jwt === '') {
            throw new RuntimeException(
                'Cannot get access token object. Please prepare the token first with generateAccessToken() or loadAccessTokenFromJWT()'
            );
        }
    }
}
