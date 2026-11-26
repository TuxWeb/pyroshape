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

namespace TuxWeb\Pyroshape\Core\Http;

use TuxWeb\Pyroshape\Core\Crypto\StringCrypt;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\Psr7\Cookies;

class EncryptedCookies
{
    /**
     * @return array<string, mixed>
     */
    public static function defaultCookieOptions(): array
    {
        return [
            'path' => '/',
            'hostonly' => true,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /**
     * @param array<string, mixed> $opts
     */
    public static function prepareNormalCookie(string $cookiename, string $cookievalue, array $opts = []): Cookies
    {
        $cookies = new Cookies();
        $cookies->setDefaults(array_replace(self::defaultCookieOptions(), self::normalizeOptions($opts)));
        $cookies->set($cookiename, ['value' => $cookievalue]);

        return $cookies;
    }

    /**
     * @param array<string, mixed> $opts
     */
    public static function prepareEncryptedCookie(
        string $cookiename,
        string $cookievalue,
        string $key,
        string $salt,
        array $opts = [],
        string $cipher = 'aes-128-gcm',
        string $hashalg = 'blake2b512'
    ): Cookies {
        $encryptedValue = StringCrypt::encryptString($cookievalue, $cipher, $hashalg, $key, $salt, true, true);

        return self::prepareNormalCookie($cookiename, $encryptedValue, $opts);
    }

    /**
     * @param array<string, mixed> $opts
     */
    public static function setEncryptedCookie(
        ResponseInterface $response,
        string $cookiename,
        string $cookievalue,
        string $key,
        string $salt,
        array $opts = [],
        string $cipher = 'aes-128-gcm',
        string $hashalg = 'blake2b512'
    ): ResponseInterface {
        $cookies = self::prepareEncryptedCookie($cookiename, $cookievalue, $key, $salt, $opts, $cipher, $hashalg);

        return $response->withAddedHeader('Set-Cookie', $cookies->toHeaders());
    }

    /**
     * @param array<string, mixed> $opts
     */
    public static function unsetEncryptedCookie(
        ResponseInterface $response,
        string $cookiename,
        array $opts = []
    ): ResponseInterface {
        $opts = array_replace(self::normalizeOptions($opts), ['expires' => 1]);
        $cookies = self::prepareNormalCookie($cookiename, '', $opts);

        return $response->withAddedHeader('Set-Cookie', $cookies->toHeaders());
    }

    public static function getRequestCookies(ServerRequestInterface $request): Cookies
    {
        return new Cookies($request->getCookieParams());
    }

    public static function getRequestCookieValue(ServerRequestInterface $request, string $cookiename): string
    {
        $cookieValue = self::getRequestCookies($request)->get($cookiename);
        if (!is_string($cookieValue) || $cookieValue === '') {
            throw new RuntimeException("Requested cookie (\"$cookiename\") does not exist");
        }

        return $cookieValue;
    }

    public static function decryptCookie(
        ServerRequestInterface $request,
        string $cookiename,
        string $key,
        string $salt,
        string $cipher = 'aes-128-gcm',
        string $hashalg = 'blake2b512'
    ): string {
        return StringCrypt::decryptString(
            self::getRequestCookieValue($request, $cookiename),
            $cipher,
            $hashalg,
            $key,
            $salt,
            true,
            true
        );
    }

    /**
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    private static function normalizeOptions(array $opts): array
    {
        $normalized = $opts;
        foreach (['httpOnly' => 'httponly', 'sameSite' => 'samesite'] as $from => $to) {
            if (array_key_exists($from, $normalized) && !array_key_exists($to, $normalized)) {
                $normalized[$to] = $normalized[$from];
                unset($normalized[$from]);
            }
        }

        return $normalized;
    }
}
