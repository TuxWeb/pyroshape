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

namespace TuxWeb\Pyroshape\Core\Crypto;

use InvalidArgumentException;
use RuntimeException;

class StringCrypt
{
    /** @var array<string, int> */
    private const AEAD_CIPHERS = [
        'aes-128-gcm' => 16,
        'aes-256-gcm' => 32,
    ];

    private static function xorScramble(string $string, string $key): string
    {
        if ($key === '') {
            throw new InvalidArgumentException('Scramble key cannot be empty');
        }

        $encrypted = '';
        $keyLength = strlen($key);
        $stringLength = strlen($string);
        for ($i = 0; $i < $stringLength; $i++) {
            $encrypted .= $string[$i] ^ $key[$i % $keyLength];
        }

        return $encrypted;
    }

    public static function encryptString(
        string $plaintext,
        string $cipher,
        string $hashalg,
        string $key,
        string $salt,
        bool $b64,
        bool $compress
    ): string {
        self::assertCipher($cipher);
        $binaryKey = self::deriveBinaryKey($key, $salt, $hashalg, $cipher);
        $ivLength = self::ivLength($cipher);
        $iv = random_bytes($ivLength);
        $tag = '';

        if ($compress) {
            $compressed = gzdeflate($plaintext, 9);
            if ($compressed === false) {
                throw new RuntimeException('Failed to compress string before encryption');
            }
            $plaintext = $compressed;
        }

        $ciphertext = openssl_encrypt($plaintext, $cipher, $binaryKey, 0, $iv, $tag);
        if ($ciphertext === false || $ciphertext === '') {
            throw new RuntimeException('Failed to encrypt string');
        }

        $crypted = self::xorScramble($iv, $salt) . $ciphertext . ':' . self::xorScramble($tag, $salt);
        if ($compress) {
            $compressed = gzdeflate($crypted, 9);
            if ($compressed === false) {
                throw new RuntimeException('Failed to compress encrypted string');
            }
            $crypted = $compressed;
        }

        if ($b64) {
            $crypted = base64_encode($crypted);
        }

        return $crypted;
    }

    public static function decryptString(
        string $crypted,
        string $cipher,
        string $hashalg,
        string $key,
        string $salt,
        bool $b64,
        bool $compress
    ): string {
        self::assertCipher($cipher);
        $binaryKey = self::deriveBinaryKey($key, $salt, $hashalg, $cipher);
        $ivLength = self::ivLength($cipher);
        $mixed = $crypted;

        if ($b64) {
            $decoded = base64_decode($mixed, true);
            if ($decoded === false) {
                throw new RuntimeException('Failed to decode encrypted string');
            }
            $mixed = $decoded;
        }

        if ($compress) {
            $inflated = @gzinflate($mixed);
            if ($inflated === false) {
                throw new RuntimeException('Failed to inflate encrypted string');
            }
            $mixed = $inflated;
        }

        if (strlen($mixed) <= $ivLength) {
            throw new RuntimeException('Encrypted string is too short');
        }

        $separatorPosition = strpos($mixed, ':', $ivLength);
        if ($separatorPosition === false || $separatorPosition === $ivLength) {
            throw new RuntimeException('Encrypted string is malformed');
        }

        $iv = self::xorScramble(substr($mixed, 0, $ivLength), $salt);
        $ciphertext = substr($mixed, $ivLength, $separatorPosition - $ivLength);
        $tag = self::xorScramble(substr($mixed, $separatorPosition + 1), $salt);

        $plaintext = openssl_decrypt($ciphertext, $cipher, $binaryKey, 0, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException('Failed to decrypt string');
        }

        if ($compress) {
            $inflated = @gzinflate($plaintext);
            if ($inflated === false) {
                throw new RuntimeException('Failed to inflate decrypted string');
            }
            $plaintext = $inflated;
        }

        return $plaintext;
    }

    private static function assertCipher(string $cipher): void
    {
        if (!array_key_exists($cipher, self::AEAD_CIPHERS)) {
            throw new InvalidArgumentException("Requested ciphersuite is not an allowed AEAD cipher: $cipher");
        }

        if (!in_array($cipher, openssl_get_cipher_methods(), true)) {
            throw new InvalidArgumentException("Requested ciphersuite is not supported by OpenSSL: $cipher");
        }
    }

    private static function ivLength(string $cipher): int
    {
        $ivLength = openssl_cipher_iv_length($cipher);
        if ($ivLength === false || $ivLength <= 0) {
            throw new InvalidArgumentException("Cannot determine IV length for ciphersuite: $cipher");
        }

        return $ivLength;
    }

    private static function deriveBinaryKey(string $key, string $salt, string $hashalg, string $cipher): string
    {
        if ($key === '') {
            throw new InvalidArgumentException('Encryption key cannot be empty');
        }

        $keyLength = self::AEAD_CIPHERS[$cipher];
        if ($hashalg === 'blake2b512' && function_exists('sodium_crypto_generichash')) {
            return sodium_crypto_generichash($salt . "\0" . $key, '', $keyLength);
        }

        if (!in_array($hashalg, hash_algos(), true)) {
            throw new InvalidArgumentException("Requested hash algorithm is not known: $hashalg");
        }

        $derived = hash_hkdf($hashalg, $key, $keyLength, 'pyroshape-encrypted-cookie', $salt);
        if (strlen($derived) !== $keyLength) {
            throw new RuntimeException('Failed to derive encryption key');
        }

        return $derived;
    }
}
