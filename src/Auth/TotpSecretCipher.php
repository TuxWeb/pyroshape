<?php

/*
 * Copyright (c) 2026 TuxWeb
 * Author: Dino Ciuffetti <dino@tuxweb.it>, TuxWeb S.r.l. - https://www.tuxweb.it/
 * Date: 2026-11-27
 * SPDX-License-Identifier: MIT
 *
 * Permission is granted to use, modify, and distribute this software under
 * the MIT License. Keep the copyright and license notices with copies or
 * substantial portions. This software is provided as is, without warranty.
 * See LICENSE for the full terms.
 */

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Auth;

use SensitiveParameter;

final class TotpSecretCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const SEED_LENGTH = 32;
    private const VERSION = 'v1';

    /** @param array<string, mixed> $settings */
    public function __construct(
        #[SensitiveParameter]
        private readonly array $settings
    ) {
    }

    public function canEncrypt(): bool
    {
        try {
            $this->key($this->settings['activeKeyId'] ?? null);

            return true;
        } catch (TotpException) {
            return false;
        }
    }

    public function encrypt(#[SensitiveParameter] string $seed, int $userId, string $purpose): string
    {
        $keyId = $this->settings['activeKeyId'] ?? null;
        $key = $this->key($keyId);
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $seed,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->associatedData($keyId, $userId, $purpose),
            self::TAG_LENGTH
        );
        if ($ciphertext === false || strlen($ciphertext) !== self::SEED_LENGTH || strlen($tag) !== self::TAG_LENGTH) {
            throw new TotpException('auth_unavailable');
        }

        return self::VERSION . '.' . $keyId . '.' . base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt(#[SensitiveParameter] ?string $envelope, int $userId, string $purpose): string
    {
        if ($envelope === null || strlen($envelope) > 116) {
            throw new TotpException('auth_unavailable');
        }

        $parts = explode('.', $envelope);
        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            throw new TotpException('auth_unavailable');
        }

        [, $keyId, $encoded] = $parts;
        $payload = base64_decode($encoded, true);
        if (
            $payload === false
            || strlen($payload) !== self::NONCE_LENGTH + self::TAG_LENGTH + self::SEED_LENGTH
            || base64_encode($payload) !== $encoded
        ) {
            throw new TotpException('auth_unavailable');
        }

        $seed = openssl_decrypt(
            substr($payload, self::NONCE_LENGTH + self::TAG_LENGTH),
            self::CIPHER,
            $this->key($keyId),
            OPENSSL_RAW_DATA,
            substr($payload, 0, self::NONCE_LENGTH),
            substr($payload, self::NONCE_LENGTH, self::TAG_LENGTH),
            $this->associatedData($keyId, $userId, $purpose)
        );
        if ($seed === false || preg_match('/^[A-Z2-7]{32}$/D', $seed) !== 1) {
            throw new TotpException('auth_unavailable');
        }

        return $seed;
    }

    private function key(mixed $keyId): string
    {
        if (
            !is_string($keyId)
            || preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $keyId) !== 1
            || !is_array($this->settings['keys'] ?? null)
            || !function_exists('openssl_get_cipher_methods')
            || !in_array(self::CIPHER, openssl_get_cipher_methods(), true)
        ) {
            throw new TotpException('auth_unavailable');
        }

        $encoded = $this->settings['keys'][$keyId] ?? null;
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($key === false || strlen($key) !== 32 || base64_encode($key) !== $encoded) {
            throw new TotpException('auth_unavailable');
        }

        return $key;
    }

    private function associatedData(string $keyId, int $userId, string $purpose): string
    {
        return implode("\0", ['pyroshape-totp', self::VERSION, $keyId, (string) $userId, $purpose]);
    }
}
