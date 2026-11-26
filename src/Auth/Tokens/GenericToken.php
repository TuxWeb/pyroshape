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

namespace TuxWeb\Pyroshape\Core\Auth\Tokens;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;

abstract class GenericToken
{
    protected ContainerInterface $container;
    protected ?string $jwt = null;
    private const RESERVED_CLAIMS = ['iss', 'aud', 'sub', 'iat', 'nbf', 'exp', 'jti'];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param array<string, mixed> $other
     */
    protected function encodeJWT(
        string $key,
        string $algorithm,
        string $issuer,
        string $audience,
        string $subject,
        int $issuedAt,
        int $notBefore,
        int $expiration,
        array $other = []
    ): string {
        $reservedOverrides = array_intersect(self::RESERVED_CLAIMS, array_keys($other));
        if ($reservedOverrides !== []) {
            throw new InvalidArgumentException(
                'JWT custom claims cannot override reserved claims: ' . implode(', ', $reservedOverrides)
            );
        }

        $claims = array_merge([
            'iss' => $issuer,
            'aud' => $audience,
            'sub' => $subject,
            'iat' => $issuedAt,
            'nbf' => $notBefore,
            'exp' => $expiration,
            'jti' => $subject . bin2hex(random_bytes(16)),
        ], $other);

        return JWT::encode($claims, $key, $algorithm);
    }

    protected function assertExpectedToken(stdClass $token, string $expectedAudience, string $expectedIssuer): void
    {
        if (($token->aud ?? null) !== $expectedAudience || ($token->iss ?? null) !== $expectedIssuer) {
            throw new RuntimeException('JWT token has unexpected audience or issuer');
        }
    }

    protected function decodeJWT(string $jwt, string $key, string $algorithm): stdClass
    {
        return JWT::decode($jwt, new Key($key, $algorithm));
    }

    /**
     * @return array<string, mixed>
     */
    protected function settings(string $name): array
    {
        $settings = $this->container->get('settings');
        if (!is_array($settings) || !isset($settings[$name]) || !is_array($settings[$name])) {
            throw new RuntimeException("Missing token settings: $name");
        }

        return $settings[$name];
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function tokenKey(array $settings, string $name): string
    {
        $key = $settings['key'] ?? $settings['secret'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new RuntimeException("Missing JWT key for $name");
        }

        return $key;
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function tokenExpire(array $settings, string $name): int
    {
        $expire = $settings['expire'] ?? $settings['ttl'] ?? null;
        if (!is_int($expire) && !(is_string($expire) && ctype_digit($expire))) {
            throw new RuntimeException("Missing JWT expiration for $name");
        }

        return (int) $expire;
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function tokenAlgorithm(array $settings, string $name): string
    {
        $algorithm = $settings['algorithm'] ?? null;
        if (!is_string($algorithm) || $algorithm === '') {
            throw new RuntimeException("Missing JWT algorithm for $name");
        }

        return $algorithm;
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function tokenIssuer(array $settings): string
    {
        $issuer = $settings['iss'] ?? 'pyroshape';
        if (!is_string($issuer) || $issuer === '') {
            throw new RuntimeException('JWT issuer must be a non-empty string');
        }

        return $issuer;
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function tokenTimeSkew(array $settings): int
    {
        $timeSkew = $settings['time_skew'] ?? 0;
        if (!is_int($timeSkew) && !(is_string($timeSkew) && ctype_digit($timeSkew))) {
            throw new RuntimeException('JWT time_skew must be an integer');
        }

        return (int) $timeSkew;
    }
}
