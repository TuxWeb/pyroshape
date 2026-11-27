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

namespace TuxWeb\Pyroshape\Core\Auth;

use PDO;
use PDOStatement;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class UserRepository
{
    private const USER_FIELDS = 'id, email, pwdhash, enabled, email_verified, nickname, name, surname, created_at, updated_at';

    private const TOTP_FIELDS = [
        'totp_enabled',
        'totp_secret_encrypted',
        'totp_enabled_at',
        'totp_disabled_at',
        'totp_last_used_step',
        'totp_recovery_code_hash',
        'auth_version',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function createUnverifiedUser(
        string $email,
        string $passwordHash,
        ?string $nickname = null,
        ?string $name = null,
        ?string $surname = null
    ): array {
        $now = $this->now();
        $normalizedEmail = self::normalizeEmail($email);
        $statement = $this->pdo->prepare(
            'INSERT INTO users
                (email, pwdhash, enabled, email_verified, nickname, name, surname, created_at, updated_at)
             VALUES
                (:email, :pwdhash, FALSE, FALSE, :nickname, :name, :surname, :created_at, :updated_at)'
        );
        $statement->execute([
            'email' => $normalizedEmail,
            'pwdhash' => $passwordHash,
            'nickname' => $nickname,
            'name' => $name,
            'surname' => $surname,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findUserById((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('Created user not found');
    }

    public function findUserByEmail(string $email): ?array
    {
        $row = $this->execute(
            'SELECT * FROM users WHERE email = :email LIMIT 1',
            ['email' => self::normalizeEmail($email)]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeAuthUserRow($row) : null;
    }

    public function findUserById(int $id): ?array
    {
        $row = $this->execute(
            'SELECT ' . self::USER_FIELDS . ' FROM users WHERE id = :id LIMIT 1',
            ['id' => $id]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeUserRow($row) : null;
    }

    public function markUserVerifiedAndEnabled(int $userId, ?string $verifiedAt = null): ?array
    {
        $now = $verifiedAt ?? $this->now();
        $verify = function (?array $user) use ($userId, $now): ?array {
            if ($user === null) {
                return null;
            }

            $changeEligibility = $user['auth_version'] !== null && (!$user['enabled'] || !$user['email_verified']);
            $version = $changeEligibility ? ', auth_version = auth_version + 1' : '';
            $this->execute(
                'UPDATE users
                 SET email_verified = TRUE, enabled = TRUE, updated_at = :updated_at' . $version . '
                 WHERE id = :id',
                ['id' => $userId, 'updated_at' => $now]
            );

            if ($changeEligibility) {
                $this->totpRepository()->consumeFlows($userId, $now);
            }

            return $this->findUserById($userId);
        };

        if ($this->pdo->inTransaction()) {
            return $verify($this->findLockedUser($userId));
        }

        return $this->withLockedUser($userId, $verify);
    }

    public function totpRepository(): TotpRepository
    {
        return new TotpRepository($this->pdo);
    }

    /** @param callable(?array): mixed $action */
    public function withLockedUser(int $userId, callable $action): mixed
    {
        $this->beginWriteTransaction();
        try {
            $result = $action($this->findLockedUser($userId));
            if (!$this->pdo->commit()) {
                throw new RuntimeException('Authentication transaction could not be committed');
            }

            return $result;
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public function storeRefreshToken(
        int $userId,
        string $jti,
        #[SensitiveParameter] string $tokenHash,
        string $released,
        string $expiration
    ): void {
        $this->execute(
            'INSERT INTO auth_tokens (users_id, jti, token_hash, released, expiration, revocation_time)
             VALUES (:users_id, :jti, :token_hash, :released, :expiration, NULL)',
            [
                'users_id' => $userId,
                'jti' => $jti,
                'token_hash' => $tokenHash,
                'released' => $released,
                'expiration' => $expiration,
            ]
        );
    }

    public function revokeRefreshTokenByJti(string $jti, ?string $revokedAt = null): bool
    {
        $statement = $this->execute(
            'UPDATE auth_tokens
             SET revocation_time = :revocation_time
             WHERE jti = :jti AND revocation_time IS NULL',
            ['jti' => $jti, 'revocation_time' => $revokedAt ?? $this->now()]
        );

        return $statement->rowCount() > 0;
    }

    public function changePasswordAndRevokeOtherRefreshTokens(
        int $userId,
        #[SensitiveParameter] string $passwordHash,
        string $keepRefreshTokenJti,
        ?string $timestamp = null
    ): void {
        $now = $timestamp ?? $this->now();
        $change = function (?array $user) use ($userId, $passwordHash, $keepRefreshTokenJti, $now): void {
            if ($user === null) {
                return;
            }

            $version = $user['auth_version'] !== null ? ', auth_version = auth_version + 1' : '';
            $this->execute(
                'UPDATE users
                 SET pwdhash = :pwdhash, updated_at = :updated_at' . $version . '
                 WHERE id = :id',
                [
                    'id' => $userId,
                    'pwdhash' => $passwordHash,
                    'updated_at' => $now,
                ]
            );

            if ($user['auth_version'] !== null) {
                $this->totpRepository()->consumeFlows($userId, $now);
            }

            $this->execute(
                'UPDATE auth_tokens
                 SET revocation_time = :revocation_time
                 WHERE users_id = :users_id
                   AND jti <> :keep_jti
                   AND revocation_time IS NULL',
                [
                    'users_id' => $userId,
                    'keep_jti' => $keepRefreshTokenJti,
                    'revocation_time' => $now,
                ]
            );
        };

        if ($this->pdo->inTransaction()) {
            $change($this->findLockedUser($userId));
            return;
        }

        $this->withLockedUser($userId, $change);
    }

    public function findValidRefreshToken(string $jti, #[SensitiveParameter] string $tokenHash, ?string $now = null): ?array
    {
        return $this->readValidRefreshToken($jti, $tokenHash, $now, false);
    }

    public function findValidRefreshTokenForUpdate(
        string $jti,
        #[SensitiveParameter] string $tokenHash,
        ?string $now = null
    ): ?array {
        return $this->readValidRefreshToken($jti, $tokenHash, $now, true);
    }

    private function readValidRefreshToken(
        string $jti,
        #[SensitiveParameter] string $tokenHash,
        ?string $now,
        bool $lock
    ): ?array {
        $row = $this->execute(
            'SELECT * FROM auth_tokens
             WHERE jti = :jti
               AND token_hash = :token_hash
               AND revocation_time IS NULL
               AND expiration >= :now
             LIMIT 1' . ($lock ? $this->lockClause() : ''),
            ['jti' => $jti, 'token_hash' => $tokenHash, 'now' => $now ?? $this->now()]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeRefreshTokenRow($row) : null;
    }

    public function refreshTokenExistsAndIsNotRevoked(string $jti, string $tokenHash, ?string $now = null): bool
    {
        return $this->findValidRefreshToken($jti, $tokenHash, $now) !== null;
    }

    public function createEmailVerificationTokenHash(int $userId, string $tokenHash, string $expiration): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO email_verification_tokens (users_id, token_hash, created_at, expiration, used_at)
             VALUES (:users_id, :token_hash, :created_at, :expiration, NULL)'
        );
        $statement->execute([
            'users_id' => $userId,
            'token_hash' => $tokenHash,
            'created_at' => $this->now(),
            'expiration' => $expiration,
        ]);
    }

    public function consumeEmailVerificationToken(string $tokenHash, ?string $now = null): ?array
    {
        $timestamp = $now ?? $this->now();

        $this->beginWriteTransaction();
        try {
            $select = $this->pdo->prepare(
                'SELECT * FROM email_verification_tokens
                 WHERE token_hash = :token_hash
                   AND used_at IS NULL
                   AND expiration >= :now
                 LIMIT 1'
            );
            $select->execute([
                'token_hash' => $tokenHash,
                'now' => $timestamp,
            ]);
            $token = $select->fetch();
            if (!is_array($token)) {
                $this->pdo->commit();

                return null;
            }

            $updateToken = $this->pdo->prepare(
                'UPDATE email_verification_tokens
                 SET used_at = :used_at
                 WHERE id = :id AND used_at IS NULL'
            );
            $updateToken->execute([
                'id' => (int) $token['id'],
                'used_at' => $timestamp,
            ]);
            if ($updateToken->rowCount() !== 1) {
                $this->pdo->commit();

                return null;
            }

            $user = $this->markUserVerifiedAndEnabled((int) $token['users_id'], $timestamp);
            $this->pdo->commit();

            return $user;
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function beginWriteTransaction(): void
    {
        // SQLite has no row locks. Reserve its write lock before reading authentication state.
        $started = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? $this->pdo->exec('BEGIN IMMEDIATE') !== false
            : $this->pdo->beginTransaction();
        if (!$started) {
            throw new RuntimeException('Authentication transaction could not be started');
        }
    }

    private function lockClause(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    private function findLockedUser(int $userId): ?array
    {
        $row = $this->execute(
            'SELECT * FROM users WHERE id = :id' . $this->lockClause(),
            ['id' => $userId]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeAuthUserRow($row) : null;
    }

    private function execute(string $sql, #[SensitiveParameter] array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false || !$statement->execute($params)) {
            throw new RuntimeException('Authentication persistence is unavailable');
        }

        return $statement;
    }

    private function normalizeAuthUserRow(array $row): array
    {
        // Determine legacy compatibility from the actual row schema, without masking database errors.
        $fieldsPresent = count(array_intersect(self::TOTP_FIELDS, array_keys($row)));
        if ($fieldsPresent === 0) {
            return $this->normalizeUserRow($row) + [
                'totp_enabled' => false,
                'auth_version' => null,
                'totp_enabled_at' => null,
                'totp_disabled_at' => null,
            ];
        }

        if ($fieldsPresent !== count(self::TOTP_FIELDS)
            || !in_array($row['totp_enabled'], [true, false, 1, 0, 't', 'f', '1', '0'], true)
            || !(is_int($row['auth_version']) || is_string($row['auth_version']) && ctype_digit($row['auth_version']))
            || (int) $row['auth_version'] < 0
        ) {
            throw new TotpException('auth_unavailable');
        }

        return $this->normalizeUserRow($row) + [
            'totp_enabled' => in_array($row['totp_enabled'], [true, 1, 't', '1'], true),
            'auth_version' => (int) $row['auth_version'],
            'totp_enabled_at' => $row['totp_enabled_at'],
            'totp_disabled_at' => $row['totp_disabled_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeUserRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'pwdhash' => isset($row['pwdhash']) ? (string) $row['pwdhash'] : null,
            'enabled' => (bool) $row['enabled'],
            'email_verified' => (bool) $row['email_verified'],
            'nickname' => $row['nickname'] !== null ? (string) $row['nickname'] : null,
            'name' => $row['name'] !== null ? (string) $row['name'] : null,
            'surname' => $row['surname'] !== null ? (string) $row['surname'] : null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRefreshTokenRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'users_id' => (int) $row['users_id'],
            'jti' => (string) $row['jti'],
            'token_hash' => (string) $row['token_hash'],
            'released' => (string) $row['released'],
            'expiration' => (string) $row['expiration'],
            'revocation_time' => $row['revocation_time'] !== null ? (string) $row['revocation_time'] : null,
        ];
    }
}
