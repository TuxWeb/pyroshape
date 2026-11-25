<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Auth\Tokens;

use Psr\Container\ContainerInterface;
use RuntimeException;

class TokenHandler
{
    private ContainerInterface $container;
    private ?RefreshToken $refreshToken = null;
    private ?AccessToken $accessToken = null;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param array<string, mixed> $other
     */
    public function generateRefreshToken(string $sub = '', array $other = []): void
    {
        $this->refreshToken = new RefreshToken($this->container);
        $this->refreshToken->generateRefreshToken($sub, $other);
    }

    public function loadRefreshTokenFromJWT(string $jwt): void
    {
        $this->refreshToken = new RefreshToken($this->container);
        $this->refreshToken->loadRefreshTokenFromJWT($jwt);
    }

    public function loadAccessTokenFromJWT(string $jwt): void
    {
        $this->accessToken = new AccessToken($this->container);
        $this->accessToken->loadAccessTokenFromJWT($jwt);
    }

    public function getRefreshToken(): RefreshToken
    {
        if ($this->refreshToken === null) {
            throw new RuntimeException(
                'Cannot get refresh token object. Please prepare the token first with generateRefreshToken() or loadRefreshTokenFromJWT()'
            );
        }

        return $this->refreshToken;
    }

    public function getAccessToken(): AccessToken
    {
        if ($this->accessToken === null) {
            throw new RuntimeException(
                'Cannot get access token object. Please prepare the token first with loadAccessTokenFromJWT()'
            );
        }

        return $this->accessToken;
    }
}
