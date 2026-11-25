<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Auth;

use PDO;
use Throwable;

class UserRepository
{
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
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => self::normalizeEmail($email)]);
        $row = $statement->fetch();

        return is_array($row) ? $this->normalizeUserRow($row) : null;
    }

    public function findUserById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->normalizeUserRow($row) : null;
    }

    public function markUserVerifiedAndEnabled(int $userId, ?string $verifiedAt = null): ?array
    {
        $now = $verifiedAt ?? $this->now();
        $statement = $this->pdo->prepare(
            'UPDATE users
             SET email_verified = TRUE, enabled = TRUE, updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $userId,
            'updated_at' => $now,
        ]);

        return $this->findUserById($userId);
    }

    public function storeRefreshToken(
        int $userId,
        string $jti,
        string $tokenHash,
        string $released,
        string $expiration
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_tokens (users_id, jti, token_hash, released, expiration, revocation_time)
             VALUES (:users_id, :jti, :token_hash, :released, :expiration, NULL)'
        );
        $statement->execute([
            'users_id' => $userId,
            'jti' => $jti,
            'token_hash' => $tokenHash,
            'released' => $released,
            'expiration' => $expiration,
        ]);
    }

    public function revokeRefreshTokenByJti(string $jti, ?string $revokedAt = null): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_tokens
             SET revocation_time = :revocation_time
             WHERE jti = :jti AND revocation_time IS NULL'
        );
        $statement->execute([
            'jti' => $jti,
            'revocation_time' => $revokedAt ?? $this->now(),
        ]);

        return $statement->rowCount() > 0;
    }

    public function changePasswordAndRevokeOtherRefreshTokens(
        int $userId,
        string $passwordHash,
        string $keepRefreshTokenJti,
        ?string $timestamp = null
    ): void {
        $now = $timestamp ?? $this->now();

        $this->pdo->beginTransaction();
        try {
            $updateUser = $this->pdo->prepare(
                'UPDATE users
                 SET pwdhash = :pwdhash, updated_at = :updated_at
                 WHERE id = :id'
            );
            $updateUser->execute([
                'id' => $userId,
                'pwdhash' => $passwordHash,
                'updated_at' => $now,
            ]);

            $revokeTokens = $this->pdo->prepare(
                'UPDATE auth_tokens
                 SET revocation_time = :revocation_time
                 WHERE users_id = :users_id
                   AND jti <> :keep_jti
                   AND revocation_time IS NULL'
            );
            $revokeTokens->execute([
                'users_id' => $userId,
                'keep_jti' => $keepRefreshTokenJti,
                'revocation_time' => $now,
            ]);

            $this->pdo->commit();
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public function findValidRefreshToken(string $jti, string $tokenHash, ?string $now = null): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM auth_tokens
             WHERE jti = :jti
               AND token_hash = :token_hash
               AND revocation_time IS NULL
               AND expiration >= :now
             LIMIT 1'
        );
        $statement->execute([
            'jti' => $jti,
            'token_hash' => $tokenHash,
            'now' => $now ?? $this->now(),
        ]);
        $row = $statement->fetch();

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

        $this->pdo->beginTransaction();
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
