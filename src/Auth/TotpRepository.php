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

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use SensitiveParameter;

/**
 * Authentication writers must hold the corresponding user lock on this connection.
 * Credential lookup methods return only a user ID so callers can acquire that lock first.
 */
class TotpRepository
{
    private const ACCOUNT_WINDOW_SECONDS = 900;
    private const ACCOUNT_MAX_FAILURES = 10;
    private const FLOW_MAX_FAILURES = 5;

    public function __construct(private PDO $pdo)
    {
    }

    public function assertAvailable(): void
    {
        $this->execute(
            'SELECT flows.flow_id, flows.purpose, flows.auth_version, flows.expires_at, flows.consumed_at,
                    flows.failed_attempts, flows.credential_hash, flows.refresh_jti, flows.remember_me,
                    flows.candidate_encrypted, flows.users_id,
                    throttle.users_id, throttle.window_started_at, throttle.failed_attempts
             FROM auth_totp_flows AS flows CROSS JOIN auth_totp_throttle AS throttle
             WHERE 1 = 0',
            []
        );
    }

    public function findFactor(int $userId): array
    {
        $row = $this->execute(
            'SELECT totp_secret_encrypted, totp_recovery_code_hash, totp_last_used_step
             FROM users WHERE id = :users_id',
            ['users_id' => $userId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('TOTP user is unavailable');
        }

        $row['totp_last_used_step'] = isset($row['totp_last_used_step']) ? (int) $row['totp_last_used_step'] : null;

        return $row;
    }

    public function findFlowUser(string $flowId, #[SensitiveParameter] string $credentialHash): ?int
    {
        $userId = $this->execute(
            'SELECT users_id FROM auth_totp_flows
             WHERE flow_id = :flow_id AND credential_hash = :credential_hash',
            ['flow_id' => $flowId, 'credential_hash' => $credentialHash]
        )->fetchColumn();

        return $userId !== false ? (int) $userId : null;
    }

    public function findUserByCredential(#[SensitiveParameter] string $credentialHash): ?int
    {
        try {
            $userId = $this->execute(
                'SELECT users_id FROM auth_totp_flows
                 WHERE credential_hash = :credential_hash AND consumed_at IS NULL',
                ['credential_hash' => $credentialHash]
            )->fetchColumn();
        } catch (PDOException $exception) {
            // Logout can encounter a stale cookie before the application has migrated.
            if ($this->missingFlowTable($exception)) {
                return null;
            }

            throw $exception;
        }

        return $userId !== false ? (int) $userId : null;
    }

    public function findFlow(int $userId, string $flowId): ?array
    {
        $row = $this->execute(
            'SELECT flow_id, users_id, purpose, auth_version, expires_at, consumed_at, failed_attempts,
                    credential_hash, refresh_jti, remember_me, candidate_encrypted
             FROM auth_totp_flows WHERE users_id = :users_id AND flow_id = :flow_id',
            ['users_id' => $userId, 'flow_id' => $flowId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $row['users_id'] = (int) $row['users_id'];
        $row['auth_version'] = (int) $row['auth_version'];
        $row['failed_attempts'] = (int) $row['failed_attempts'];
        $row['remember_me'] = $row['remember_me'] === null
            ? null
            : in_array($row['remember_me'], [true, 1, '1', 't'], true);

        return $row;
    }

    public function createFlow(
        string $flowId,
        int $userId,
        string $purpose,
        int $authVersion,
        string $expiresAt,
        #[SensitiveParameter] ?string $credentialHash = null,
        ?string $refreshJti = null,
        ?bool $rememberMe = null,
        #[SensitiveParameter] ?string $candidateEncrypted = null
    ): void {
        $rememberMeValue = match ($rememberMe) {
            true => 'TRUE',
            false => 'FALSE',
            null => 'NULL',
        };
        $this->write(
            'INSERT INTO auth_totp_flows
                (flow_id, users_id, purpose, auth_version, expires_at, credential_hash, refresh_jti, remember_me,
                 candidate_encrypted)
             VALUES (:flow_id, :users_id, :purpose, :auth_version, :expires_at, :credential_hash, :refresh_jti, '
                . $rememberMeValue . ', :candidate_encrypted)',
            [
                'flow_id' => $flowId,
                'users_id' => $userId,
                'purpose' => $purpose,
                'auth_version' => $authVersion,
                'expires_at' => $expiresAt,
                'credential_hash' => $credentialHash,
                'refresh_jti' => $refreshJti,
                'candidate_encrypted' => $candidateEncrypted,
            ]
        );
    }

    public function consumeFlow(int $userId, string $flowId, string $now): void
    {
        $this->write(
            'UPDATE auth_totp_flows SET consumed_at = :consumed_at, candidate_encrypted = NULL
             WHERE users_id = :users_id AND flow_id = :flow_id AND consumed_at IS NULL',
            ['users_id' => $userId, 'flow_id' => $flowId, 'consumed_at' => $now]
        );
    }

    public function consumeFlows(int $userId, string $now, ?string $purpose = null): void
    {
        $where = 'WHERE users_id = :users_id AND consumed_at IS NULL';
        $params = ['users_id' => $userId, 'consumed_at' => $now];
        if ($purpose !== null) {
            $where .= ' AND purpose = :purpose';
            $params['purpose'] = $purpose;
        }

        $this->write(
            'UPDATE auth_totp_flows SET consumed_at = :consumed_at, candidate_encrypted = NULL ' . $where,
            $params
        );
    }

    public function consumeCredential(int $userId, #[SensitiveParameter] string $credentialHash, string $now): void
    {
        $this->write(
            'UPDATE auth_totp_flows SET consumed_at = :consumed_at, candidate_encrypted = NULL
             WHERE users_id = :users_id AND credential_hash = :credential_hash AND consumed_at IS NULL',
            ['users_id' => $userId, 'credential_hash' => $credentialHash, 'consumed_at' => $now]
        );
    }

    public function hasActiveFlow(int $userId, string $purpose, string $now, int $authVersion): bool
    {
        return $this->execute(
            'SELECT 1 FROM auth_totp_flows
             WHERE users_id = :users_id AND purpose = :purpose AND auth_version = :auth_version
               AND consumed_at IS NULL AND expires_at > :now
             LIMIT 1',
            ['users_id' => $userId, 'purpose' => $purpose, 'auth_version' => $authVersion, 'now' => $now]
        )->fetchColumn() !== false;
    }

    public function retryAfter(int $userId, int $now): int
    {
        $startedAt = $this->execute(
            'SELECT window_started_at FROM auth_totp_throttle
             WHERE users_id = :users_id AND failed_attempts >= :max_failures',
            ['users_id' => $userId, 'max_failures' => self::ACCOUNT_MAX_FAILURES]
        )->fetchColumn();
        if ($startedAt === false) {
            return 0;
        }

        $deadline = (new DateTimeImmutable((string) $startedAt, new DateTimeZone('UTC')))->getTimestamp()
            + self::ACCOUNT_WINDOW_SECONDS;

        return max(0, $deadline - $now);
    }

    public function recordFailure(int $userId, int $now, ?string $flowId = null): void
    {
        $timestamp = gmdate('Y-m-d H:i:s', $now);
        $cutoff = gmdate('Y-m-d H:i:s', $now - self::ACCOUNT_WINDOW_SECONDS);
        $upsert = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'ON DUPLICATE KEY UPDATE'
            : 'ON CONFLICT (users_id) DO UPDATE SET';
        $this->write(
            'INSERT INTO auth_totp_throttle (users_id, window_started_at, failed_attempts)
             VALUES (:users_id, :initial_start, 1) ' . $upsert . '
             failed_attempts = CASE
                 WHEN auth_totp_throttle.window_started_at <= :count_cutoff THEN 1
                 WHEN auth_totp_throttle.failed_attempts < :max_failures THEN auth_totp_throttle.failed_attempts + 1
                 ELSE auth_totp_throttle.failed_attempts
             END,
             window_started_at = CASE
                 WHEN auth_totp_throttle.window_started_at <= :start_cutoff THEN :next_start
                 ELSE auth_totp_throttle.window_started_at
             END',
            [
                'users_id' => $userId,
                'initial_start' => $timestamp,
                'count_cutoff' => $cutoff,
                'max_failures' => self::ACCOUNT_MAX_FAILURES,
                'start_cutoff' => $cutoff,
                'next_start' => $timestamp,
            ]
        );

        if ($flowId !== null) {
            $this->write(
                'UPDATE auth_totp_flows
                 SET consumed_at = CASE WHEN failed_attempts + 1 >= :consume_limit THEN :consumed_at ELSE consumed_at END,
                     candidate_encrypted = CASE WHEN failed_attempts + 1 >= :erase_limit THEN NULL
                                                ELSE candidate_encrypted END,
                     failed_attempts = failed_attempts + 1
                 WHERE users_id = :users_id AND flow_id = :flow_id AND consumed_at IS NULL',
                [
                    'users_id' => $userId,
                    'flow_id' => $flowId,
                    'consumed_at' => $timestamp,
                    'consume_limit' => self::FLOW_MAX_FAILURES,
                    'erase_limit' => self::FLOW_MAX_FAILURES,
                ]
            );
        }
    }

    public function advanceStep(int $userId, int $step): bool
    {
        return $this->write(
            'UPDATE users SET totp_last_used_step = :next_step, updated_at = updated_at
             WHERE id = :users_id AND totp_enabled = TRUE
               AND (totp_last_used_step IS NULL OR totp_last_used_step < :matched_step)',
            ['users_id' => $userId, 'next_step' => $step, 'matched_step' => $step]
        )->rowCount() === 1;
    }

    public function activate(
        int $userId,
        #[SensitiveParameter] string $ciphertext,
        #[SensitiveParameter] string $recoveryHash,
        int $step,
        string $now
    ): void {
        $this->write(
            'UPDATE users
             SET totp_enabled_at = CASE WHEN totp_enabled = TRUE THEN totp_enabled_at ELSE :enabled_at END,
                 totp_enabled = TRUE,
                 totp_secret_encrypted = :ciphertext,
                 totp_recovery_code_hash = :recovery_hash,
                 totp_last_used_step = :used_step,
                 auth_version = auth_version + 1,
                 updated_at = :updated_at
             WHERE id = :users_id',
            [
                'users_id' => $userId,
                'enabled_at' => $now,
                'ciphertext' => $ciphertext,
                'recovery_hash' => $recoveryHash,
                'used_step' => $step,
                'updated_at' => $now,
            ]
        );
        $this->revokeSessionsAndFlows($userId, $now);
    }

    public function disable(int $userId, string $now): void
    {
        $this->write(
            'UPDATE users
             SET totp_enabled = FALSE,
                 totp_secret_encrypted = NULL,
                 totp_recovery_code_hash = NULL,
                 totp_last_used_step = NULL,
                 totp_disabled_at = :disabled_at,
                 auth_version = auth_version + 1,
                 updated_at = :updated_at
             WHERE id = :users_id',
            ['users_id' => $userId, 'disabled_at' => $now, 'updated_at' => $now]
        );
        $this->revokeSessionsAndFlows($userId, $now);
    }

    public function deleteExpiredFlows(string $now, int $limit = 100): int
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('TOTP cleanup batch size must be positive');
        }

        return $this->execute(
            'DELETE FROM auth_totp_flows WHERE flow_id IN (
                SELECT flow_id FROM (
                    SELECT flow_id FROM auth_totp_flows WHERE expires_at <= :now
                    ORDER BY expires_at, flow_id LIMIT ' . $limit . '
                ) AS expired_flows
             )',
            ['now' => $now]
        )->rowCount();
    }

    private function revokeSessionsAndFlows(int $userId, string $now): void
    {
        $this->consumeFlows($userId, $now);
        $this->write(
            'UPDATE auth_tokens SET revocation_time = :revoked_at
             WHERE users_id = :users_id AND revocation_time IS NULL',
            ['users_id' => $userId, 'revoked_at' => $now]
        );
    }

    private function write(string $sql, #[SensitiveParameter] array $params): PDOStatement
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('TOTP writes require an authentication transaction');
        }

        return $this->execute($sql, $params);
    }

    private function execute(string $sql, #[SensitiveParameter] array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('TOTP persistence is unavailable');
        }

        foreach ($params as $name => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            if (!$statement->bindValue(':' . $name, $value, $type)) {
                throw new RuntimeException('TOTP persistence is unavailable');
            }
        }

        if (!$statement->execute()) {
            throw new RuntimeException('TOTP persistence is unavailable');
        }

        return $statement;
    }

    private function missingFlowTable(PDOException $exception): bool
    {
        return in_array($exception->getCode(), ['42P01', '42S02'], true)
            || ($exception->getCode() === 'HY000'
                && ($exception->errorInfo[2] ?? null) === 'no such table: auth_totp_flows');
    }
}
