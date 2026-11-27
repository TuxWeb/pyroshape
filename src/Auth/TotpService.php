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

use Closure;
use OTPHP\Exception\OTPExceptionInterface;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;
use TuxWeb\Pyroshape\Core\Http\EncryptedCookies;

/** Coordinates TOTP proofs and changes using the user row as the serialization point. */
final class TotpService
{
    private const LOGIN_TTL = 300;
    private const CANDIDATE_TTL = 600;
    private const INTERVAL = 30;
    private const ACTIVE_FACTOR = 'active';

    private ?UserRepository $users = null;
    private ?TotpRepository $repository = null;
    private readonly TotpSecretCipher $cipher;
    private readonly array $settings;

    /** @param array<string, mixed> $appSettings */
    public function __construct(
        private readonly Closure $resolveUsers,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
        #[SensitiveParameter]
        private readonly array $appSettings
    ) {
        $config = $appSettings['auth']['totp'] ?? null;
        $this->settings = is_array($config) ? $config : [];
        $this->cipher = new TotpSecretCipher($this->settings);
    }

    private function users(): UserRepository
    {
        return $this->users ??= ($this->resolveUsers)();
    }

    private function repository(): TotpRepository
    {
        return $this->repository ??= $this->users()->totpRepository();
    }

    /** @param array<string, mixed> $user */
    public static function enabled(array $user): bool
    {
        if (
            !is_bool($user['totp_enabled'] ?? null)
            || !array_key_exists('auth_version', $user)
            || ($user['auth_version'] !== null && !is_int($user['auth_version']))
            || ($user['totp_enabled'] && $user['auth_version'] === null)
        ) {
            throw new TotpException('auth_unavailable');
        }

        return $user['totp_enabled'];
    }

    /**
     * Starts a login challenge under the caller's user lock and transaction.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function startLogin(array $user, bool $rememberMe): array
    {
        $userId = (int) $user['id'];
        $now = $this->timestamp();
        $this->requireAttemptsAvailable($userId, $now, 'login');
        $factor = $this->repository()->findFactor($userId);
        $this->cipher->decrypt($factor['totp_secret_encrypted'], $userId, self::ACTIVE_FACTOR);
        $this->cookieName();

        $flowId = $this->randomCredential();
        $credential = $this->randomCredential();
        $expiresAt = $now + self::LOGIN_TTL;
        $this->repository()->createFlow(
            $flowId,
            $userId,
            'login',
            $user['auth_version'],
            $this->dbTime($expiresAt),
            credentialHash: hash('sha256', $credential),
            rememberMe: $rememberMe
        );

        return [
            'authenticated' => false,
            'next' => 'totp_required',
            'flowId' => $flowId,
            'expiresAt' => $this->isoTime($expiresAt),
            'credential' => $credential,
        ];
    }

    /**
     * @param callable(array<string, mixed>, bool): mixed $createSession
     * @return array<string, mixed>
     */
    public function completeLogin(
        ServerRequestInterface $request,
        string $flowId,
        #[SensitiveParameter]
        string $code,
        callable $createSession
    ): array {
        $this->requireCode($code);

        return $this->withCookieFlow(
            $request,
            $flowId,
            'login',
            function (array $user, array $flow, int $now) use ($code, $createSession): array|TotpException {
                $userId = (int) $user['id'];
                $rememberMe = $flow['remember_me'];
                if (!is_bool($rememberMe)) {
                    throw new TotpException('auth_unavailable');
                }

                if (!$this->consumeActiveStep($userId, $code, $now)) {
                    return $this->failedProof($userId, $now, $flow['flow_id']);
                }

                $this->repository()->consumeFlow($userId, $flow['flow_id'], $this->dbTime($now));

                return [
                    'user' => $user,
                    'rememberMe' => $rememberMe,
                    'session' => $createSession($user, $rememberMe),
                ];
            }
        );
    }

    /** @return array<string, mixed> */
    public function status(CurrentSession $session): array
    {
        return $this->withSession($session, function (array $user): array {
            $status = $this->userStatus($user);
            if ($status['enrollmentAvailable']) {
                $this->repository()->assertAvailable();
            }

            return $status;
        });
    }

    /** @return array<string, mixed> */
    public function setup(
        CurrentSession $session,
        #[SensitiveParameter]
        string $password,
        #[SensitiveParameter]
        ?string $code
    ): array {
        if ($code !== null) {
            $this->requireCode($code);
        }

        $passwordHash = $this->reauthenticate($session, $password);

        return $this->withSession($session, function (array $user, int $now) use ($session, $passwordHash, $code): array|TotpException {
            $this->requireSetupAvailable($user);
            $this->requireAttemptsAvailable($session->userId, $now, 'setup');
            $this->requirePasswordUnchanged($user, $passwordHash);

            if (self::enabled($user)) {
                $this->requireCode($code);
                if (!$this->consumeActiveStep($session->userId, $code, $now)) {
                    return $this->failedProof($session->userId, $now);
                }
            }

            $this->repository()->consumeFlows($session->userId, $this->dbTime($now), 'setup');

            return $this->createCandidate($user, 'setup', $now, refreshJti: $session->refreshJti);
        });
    }

    /** @return array<string, mixed> */
    public function confirmSetup(
        CurrentSession $session,
        string $flowId,
        #[SensitiveParameter]
        string $code
    ): array {
        $this->requireFlowId($flowId);
        $this->requireCode($code);
        $result = $this->withSession(
            $session,
            function (array $user, int $now) use ($session, $flowId, $code): array|TotpException {
                $this->requireSetupAvailable($user);
                $flow = $this->flow($user, $flowId, 'setup', $now);
                if ($flow['refresh_jti'] !== $session->refreshJti) {
                    throw new TotpException('flow_unavailable');
                }

                $this->requireAttemptsAvailable($session->userId, $now, 'setup_confirm');

                return $this->activateCandidate($user, $flow, $code, $now);
            }
        );

        return $this->factorChanged($result);
    }

    /** @return array<string, mixed> */
    public function disable(
        CurrentSession $session,
        #[SensitiveParameter]
        string $password,
        #[SensitiveParameter]
        string $code
    ): array {
        $this->requireCode($code);
        $passwordHash = $this->reauthenticate($session, $password);

        $result = $this->withSession($session, function (array $user, int $now) use ($session, $passwordHash, $code): array|TotpException {
            if (!self::enabled($user)) {
                throw new TotpException('invalid_code');
            }

            $this->requireAttemptsAvailable($session->userId, $now, 'disable');
            $this->requirePasswordUnchanged($user, $passwordHash);
            if (!$this->consumeActiveStep($session->userId, $code, $now)) {
                return $this->failedProof($session->userId, $now);
            }

            $this->repository()->disable($session->userId, $this->dbTime($now));
            $user['totp_enabled'] = false;
            $user['totp_disabled_at'] = $this->dbTime($now);

            return ['user' => $user, 'event' => 'disabled'];
        });

        return $this->factorChanged($result);
    }

    /** @return array<string, mixed> */
    public function recover(
        ServerRequestInterface $request,
        string $flowId,
        #[SensitiveParameter]
        string $recoveryCode
    ): array {
        if (trim($recoveryCode) === '' || strlen($recoveryCode) > 128) {
            throw new TotpException('invalid_request');
        }

        $codeHash = hash('sha256', str_replace('-', '', strtoupper(trim($recoveryCode))));
        $result = $this->withCookieFlow(
            $request,
            $flowId,
            'login',
            function (array $user, array $flow, int $now) use ($codeHash): array|TotpException {
                $userId = (int) $user['id'];
                $factor = $this->repository()->findFactor($userId);
                $expected = $factor['totp_recovery_code_hash'];
                if ($expected === null || !hash_equals($expected, $codeHash)) {
                    return $this->failedProof($userId, $now, $flow['flow_id']);
                }

                if ($this->repository()->hasActiveFlow($userId, 'recovery', $this->dbTime($now), $user['auth_version'])) {
                    throw new TotpException('flow_unavailable');
                }

                $credential = $this->randomCredential();
                $candidate = $this->createCandidate($user, 'recovery', $now, credential: $credential);
                $this->repository()->consumeFlow($userId, $flow['flow_id'], $this->dbTime($now));

                return ['userId' => $userId, 'credential' => $credential] + $candidate;
            },
            'recovery'
        );
        $this->audit('recovery_started', $result['userId']);
        unset($result['userId']);

        return $result;
    }

    /** @return array<string, mixed> */
    public function confirmRecovery(
        ServerRequestInterface $request,
        string $flowId,
        #[SensitiveParameter]
        string $code
    ): array {
        $this->requireCode($code);
        $result = $this->withCookieFlow(
            $request,
            $flowId,
            'recovery',
            fn (array $user, array $flow, int $now): array|TotpException => $this->activateCandidate($user, $flow, $code, $now),
            'recovery_confirm'
        );

        return $this->factorChanged($result);
    }

    public function closePresentedChallenge(
        ServerRequestInterface $request,
        ResponseInterface $response,
        bool $clearCookie = true
    ): ResponseInterface {
        if ($request->getCookieParams() === []) {
            return $response;
        }

        $name = $this->cookieName();
        $credential = $request->getCookieParams()[$name] ?? null;
        if ($credential === null) {
            return $response;
        }

        if (is_string($credential) && $this->validOpaqueId($credential)) {
            $this->run(function () use ($credential): void {
                $hash = hash('sha256', $credential);
                $userId = $this->repository()->findUserByCredential($hash);
                if ($userId !== null) {
                    $this->users()->withLockedUser($userId, function (?array $user) use ($userId, $hash): void {
                        if ($user !== null) {
                            $this->repository()->consumeCredential($userId, $hash, $this->dbTime($this->timestamp()));
                        }
                    });
                }
            });
        }

        return $clearCookie ? $this->clearChallengeCookie($response) : $response;
    }

    public function setChallengeCookie(
        ResponseInterface $response,
        #[SensitiveParameter]
        string $credential,
        bool $recovery = false
    ): ResponseInterface {
        $options = $this->cookieSettings();
        $options['expires'] = time() + ($recovery ? self::CANDIDATE_TTL : self::LOGIN_TTL);
        $cookie = EncryptedCookies::prepareNormalCookie($this->cookieName(), $credential, $options);

        return $response->withAddedHeader('Set-Cookie', $cookie->toHeaders());
    }

    public function clearChallengeCookie(ResponseInterface $response): ResponseInterface
    {
        return EncryptedCookies::unsetEncryptedCookie($response, $this->cookieName(), $this->cookieSettings());
    }

    /**
     * @param callable(array<string, mixed>, int): (array<string, mixed>|TotpException) $action
     * @return array<string, mixed>
     */
    private function withSession(CurrentSession $session, callable $action): array
    {
        return $this->run(fn (): mixed => $this->users()->withLockedUser(
            $session->userId,
            function (?array $user) use ($session, $action): array|TotpException {
                $stored = $this->users()->findValidRefreshTokenForUpdate(
                    $session->refreshJti,
                    $session->refreshTokenHash(),
                    $this->dbTime(time())
                );
                if (!$this->eligible($user) || $stored === null || (int) $stored['users_id'] !== $session->userId) {
                    throw new TotpException('current_session_required');
                }

                self::enabled($user);

                return $action($user, $this->timestamp());
            }
        ));
    }

    /**
     * @param callable(array<string, mixed>, array<string, mixed>, int): (array<string, mixed>|TotpException) $action
     * @return array<string, mixed>
     */
    private function withCookieFlow(
        ServerRequestInterface $request,
        string $flowId,
        string $purpose,
        callable $action,
        ?string $operation = null
    ): array {
        $this->requireFlowId($flowId);
        $credential = $request->getCookieParams()[$this->cookieName()] ?? null;
        if (!is_string($credential) || !$this->validOpaqueId($credential)) {
            throw new TotpException('flow_unavailable');
        }

        $hash = hash('sha256', $credential);

        return $this->run(function () use ($flowId, $hash, $purpose, $action, $operation): mixed {
            $userId = $this->repository()->findFlowUser($flowId, $hash);
            if ($userId === null) {
                throw new TotpException('flow_unavailable');
            }

            return $this->users()->withLockedUser(
                $userId,
                function (?array $user) use ($flowId, $hash, $purpose, $action, $operation, $userId): array|TotpException {
                    if (!$this->eligible($user) || !self::enabled($user)) {
                        throw new TotpException('flow_unavailable');
                    }

                    $now = $this->timestamp();
                    $flow = $this->flow($user, $flowId, $purpose, $now);
                    if (!is_string($flow['credential_hash']) || !hash_equals($flow['credential_hash'], $hash)) {
                        throw new TotpException('flow_unavailable');
                    }

                    $this->requireAttemptsAvailable($userId, $now, $operation ?? $purpose);

                    return $action($user, $flow, $now);
                }
            );
        });
    }

    private function run(callable $action): mixed
    {
        try {
            $result = $action();
        } catch (TotpException $exception) {
            throw $exception;
        } catch (RuntimeException | ContainerExceptionInterface | OTPExceptionInterface) {
            throw new TotpException('auth_unavailable');
        }

        // A proof failure returns from the transaction so the attempt counts commit.
        if ($result instanceof TotpException) {
            throw $result;
        }

        return $result;
    }

    /** @param array<string, mixed>|null $user */
    private function eligible(?array $user): bool
    {
        return $user !== null && $user['enabled'] && $user['email_verified'];
    }

    /** @param array<string, mixed> $user */
    private function requireSetupAvailable(array $user): void
    {
        if (!self::enabled($user) && !$this->enrollmentAllowed()) {
            throw new TotpException('enrollment_unavailable');
        }
        if ($user['auth_version'] === null || !$this->provisioningAvailable()) {
            throw new TotpException('auth_unavailable');
        }
    }

    private function enrollmentAllowed(): bool
    {
        return ($this->settings['allowEnrollment'] ?? false) === true;
    }

    private function provisioningAvailable(): bool
    {
        $issuer = $this->settings['issuer'] ?? null;

        return is_string($issuer)
            && trim($issuer) !== ''
            && !preg_match('/[:\x00-\x1f\x7f]|%3a/i', $issuer)
            && $this->cipher->canEncrypt();
    }

    /** @param array<string, mixed> $user */
    private function userStatus(array $user): array
    {
        return [
            'enrollmentAvailable' => $this->enrollmentAllowed() && $user['auth_version'] !== null && $this->provisioningAvailable(),
            'enabled' => self::enabled($user),
            'enabledAt' => $this->statusDate($user['totp_enabled_at'] ?? null),
            'disabledAt' => $this->statusDate($user['totp_disabled_at'] ?? null),
        ];
    }

    private function reauthenticate(CurrentSession $session, #[SensitiveParameter] string $password): ?string
    {
        if ($password === '' || strlen($password) > 72) {
            throw new TotpException('invalid_request');
        }

        return $this->run(function () use ($session, $password): ?string {
            $user = $this->users()->findUserById($session->userId);
            $hash = $user['pwdhash'] ?? null;

            return is_string($hash) && password_verify($password, $hash) ? $hash : null;
        });
    }

    /** @param array<string, mixed> $user */
    private function requirePasswordUnchanged(array $user, #[SensitiveParameter] ?string $passwordHash): void
    {
        if ($passwordHash === null || $user['pwdhash'] !== $passwordHash) {
            throw new TotpException('invalid_credentials');
        }
    }

    /** @param array<string, mixed> $user */
    private function flow(array $user, string $flowId, string $purpose, int $now): array
    {
        $flow = $this->repository()->findFlow((int) $user['id'], $flowId);
        if (
            $flow === null
            || $flow['purpose'] !== $purpose
            || $flow['auth_version'] !== $user['auth_version']
            || $flow['consumed_at'] !== null
            || $flow['expires_at'] <= $this->dbTime($now)
        ) {
            throw new TotpException('flow_unavailable');
        }

        return $flow;
    }

    private function requireAttemptsAvailable(int $userId, int $now, string $operation): void
    {
        $retryAfter = $this->repository()->retryAfter($userId, $now);
        if ($retryAfter > 0) {
            $this->audit('rate_limited', $userId, 'rejected', ['operation' => $operation]);
            throw new TotpException('rate_limited', $retryAfter);
        }
    }

    private function failedProof(int $userId, int $now, ?string $flowId = null): TotpException
    {
        $this->repository()->recordFailure($userId, $now, $flowId);

        return new TotpException('invalid_code');
    }

    private function consumeActiveStep(int $userId, #[SensitiveParameter] string $code, int $now): bool
    {
        $factor = $this->repository()->findFactor($userId);
        $seed = $this->cipher->decrypt($factor['totp_secret_encrypted'], $userId, self::ACTIVE_FACTOR);
        $step = $this->matchStep($seed, $code, $now);

        return $step !== null && $this->repository()->advanceStep($userId, $step);
    }

    private function matchStep(
        #[SensitiveParameter] string $seed,
        #[SensitiveParameter] string $code,
        int $now
    ): ?int {
        $totp = TOTP::create($seed, self::INTERVAL, 'sha1', 6, clock: $this->clock);
        $step = intdiv($now, self::INTERVAL);

        // Pick a single interval even in the rare case of adjacent equal codes.
        foreach ([$step + 1, $step, $step - 1] as $candidate) {
            if ($candidate >= 0 && $totp->verify($code, $candidate * self::INTERVAL)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function createCandidate(
        array $user,
        string $purpose,
        int $now,
        ?string $refreshJti = null,
        #[SensitiveParameter]
        ?string $credential = null
    ): array {
        if (!$this->provisioningAvailable()) {
            throw new TotpException('auth_unavailable');
        }

        $totp = TOTP::create(period: self::INTERVAL, digest: 'sha1', digits: 6, clock: $this->clock, secretSize: 20);
        $totp->setIssuer($this->settings['issuer']);
        $label = $user['email'];
        $totp->setLabel(preg_match('/[:\x00-\x1f\x7f]|%3a/i', $label) ? 'User ' . $user['id'] : $label);
        $seed = $totp->getSecret();
        $flowId = $this->randomCredential();
        $expiresAt = $now + self::CANDIDATE_TTL;
        $userId = (int) $user['id'];
        $this->repository()->createFlow(
            $flowId,
            $userId,
            $purpose,
            $user['auth_version'],
            $this->dbTime($expiresAt),
            credentialHash: $credential === null ? null : hash('sha256', $credential),
            refreshJti: $refreshJti,
            candidateEncrypted: $this->cipher->encrypt($seed, $userId, $purpose . ':' . $flowId)
        );

        return [
            'flowId' => $flowId,
            'expiresAt' => $this->isoTime($expiresAt),
            'provisioningUri' => $totp->getProvisioningUri(),
            'manualKey' => $seed,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $flow
     * @return array<string, mixed>|TotpException
     */
    private function activateCandidate(
        array $user,
        #[SensitiveParameter]
        array $flow,
        #[SensitiveParameter]
        string $code,
        int $now
    ): array|TotpException {
        $userId = (int) $user['id'];
        $purpose = $flow['purpose'];
        $seed = $this->cipher->decrypt($flow['candidate_encrypted'], $userId, $purpose . ':' . $flow['flow_id']);
        $step = $this->matchStep($seed, $code, $now);
        if ($step === null) {
            return $this->failedProof($userId, $now, $flow['flow_id']);
        }

        $newCode = strtoupper(bin2hex(random_bytes(16)));
        $this->repository()->activate(
            $userId,
            $this->cipher->encrypt($seed, $userId, self::ACTIVE_FACTOR),
            hash('sha256', $newCode),
            $step,
            $this->dbTime($now)
        );

        $event = 'enrolled';
        if ($purpose === 'recovery') {
            $event = 'recovered';
        } elseif ($user['totp_enabled']) {
            $event = 'replaced';
        }

        if (!$user['totp_enabled']) {
            $user['totp_enabled_at'] = $this->dbTime($now);
        }
        $user['totp_enabled'] = true;

        return [
            'user' => $user,
            'event' => $event,
            'recoveryCode' => implode('-', str_split($newCode, 4)),
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function factorChanged(#[SensitiveParameter] array $result): array
    {
        $user = $result['user'];
        $this->audit($result['event'], (int) $user['id']);
        unset($result['user'], $result['event']);

        return $this->userStatus($user) + ['reauthenticationRequired' => true] + $result;
    }

    /** @param array<string, mixed> $context */
    private function audit(string $event, int $userId, string $outcome = 'succeeded', array $context = []): void
    {
        $this->logger->notice('auth.totp_' . $event, ['user_id' => $userId, 'outcome' => $outcome] + $context);
    }

    private function requireCode(#[SensitiveParameter] ?string $code): void
    {
        if ($code === null || preg_match('/^[0-9]{6}$/D', $code) !== 1) {
            throw new TotpException('invalid_request');
        }
    }

    private function requireFlowId(string $id): void
    {
        if (!$this->validOpaqueId($id)) {
            throw new TotpException('invalid_request');
        }
    }

    private function validOpaqueId(#[SensitiveParameter] string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/D', $value) === 1;
    }

    private function randomCredential(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function timestamp(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    private function dbTime(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function isoTime(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    private function statusDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value . ' UTC');
        if ($timestamp === false) {
            throw new TotpException('auth_unavailable');
        }

        return $this->isoTime($timestamp);
    }

    private function cookieName(): string
    {
        $name = $this->settings['challengeCookieName'] ?? 'pyroshape_totp_challenge';
        $login = $this->appSettings['login_cookie'] ?? [];
        $loginName = $login['cookiename'] ?? $login['name'] ?? null;
        if (!is_string($name) || preg_match('/^[A-Za-z0-9_-]+$/D', $name) !== 1 || $name === $loginName) {
            throw new TotpException('auth_unavailable');
        }

        return $name;
    }

    /** @return array<string, mixed> */
    private function cookieSettings(): array
    {
        $basePath = (string) ($this->appSettings['basePath'] ?? '');
        $environment = strtolower((string) ($this->appSettings['environment'] ?? 'development'));

        return [
            'path' => rtrim($basePath, '/') . '/api/auth',
            'secure' => in_array($environment, ['production', 'prod'], true) || ($this->settings['secureCookie'] ?? true) !== false,
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }
}
