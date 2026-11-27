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

use TuxWeb\Pyroshape\Core\Auth\Tokens\AccessToken;
use TuxWeb\Pyroshape\Core\Auth\Tokens\RefreshToken;
use TuxWeb\Pyroshape\Core\Auth\Tokens\TokenHandler;
use TuxWeb\Pyroshape\Core\Http\EncryptedCookies;
use Firebase\JWT\ExpiredException;
use InvalidArgumentException;
use JsonException;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class AuthService
{
    private const DUMMY_BCRYPT_HASH = '$2y$12$bNmI7bFzmrJZM12sQOMC5eNZ0BBFxS0.XdKyRcKLbC8vcLMGHWkPq';

    private ?UserRepository $users;
    private LoggerInterface $logger;
    private ?TotpService $totp = null;

    public function __construct(private ContainerInterface $container, ?UserRepository $users = null)
    {
        $this->users = $users;
        $this->logger = $container->has(LoggerInterface::class)
            ? $container->get(LoggerInterface::class)
            : new NullLogger();
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    public function registerLocalUser(string $email, string $password, array $profile = []): array
    {
        $normalizedEmail = UserRepository::normalizeEmail($email);
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email address is invalid');
        }

        $this->assertValidPassword($password);

        $user = $this->users()->createUnverifiedUser(
            $normalizedEmail,
            password_hash($password, PASSWORD_BCRYPT),
            $this->nullableString($profile['nickname'] ?? null),
            $this->nullableString($profile['name'] ?? null),
            $this->nullableString($profile['surname'] ?? null)
        );

        $verificationToken = $this->randomToken();
        $this->users()->createEmailVerificationTokenHash(
            (int) $user['id'],
            $this->tokenHash($verificationToken),
            $this->dateFromNow($this->emailVerificationTtl())
        );

        $payload = $this->safeUserFields($user);
        if ($this->devExposeVerificationToken()) {
            $payload['verificationToken'] = $verificationToken;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyEmailToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('Verification token is required');
        }

        $user = $this->users()->consumeEmailVerificationToken($this->tokenHash($token), $this->now());
        if ($user === null) {
            throw new RuntimeException('Verification token is invalid, expired, or already used');
        }

        return ['verified' => true, 'authenticated' => false] + $this->safeUserFields($user);
    }

    /**
     * An enrolled user gets a pending outcome. Browser callers use loginHttp to open a challenge.
     *
     * @return array<string, mixed>
     */
    public function login(
        string $email,
        #[SensitiveParameter]
        string $password,
        ResponseInterface $response,
        bool $rememberMe = false
    ): array {
        return $this->passwordLogin($email, $password, $response, $rememberMe);
    }

    /**
     * @param callable(ServerRequestInterface): bool $requireOrigin
     * @return array<string, mixed>
     */
    public function loginHttp(
        string $email,
        #[SensitiveParameter]
        string $password,
        ResponseInterface $response,
        bool $rememberMe,
        ServerRequestInterface $request,
        callable $requireOrigin
    ): array {
        return $this->passwordLogin($email, $password, $response, $rememberMe, $request, $requireOrigin);
    }

    /**
     * @param (callable(ServerRequestInterface): bool)|null $requireOrigin
     * @return array<string, mixed>
     */
    private function passwordLogin(
        string $email,
        #[SensitiveParameter]
        string $password,
        ResponseInterface $response,
        bool $rememberMe,
        ?ServerRequestInterface $request = null,
        ?callable $requireOrigin = null
    ): array {
        $this->assertValidPassword($password);

        try {
            $user = $this->users()->findUserByEmail($email);
            $passwordHash = is_string($user['pwdhash'] ?? null) ? $user['pwdhash'] : self::DUMMY_BCRYPT_HASH;
            $validPassword = password_verify($password, $passwordHash);
            if (!is_string($user['pwdhash'] ?? null) || !$validPassword) {
                throw new AuthException('Invalid email or password', 401);
            }

            $this->requireLoginEligible($user);
            $totpEnabled = TotpService::enabled($user);
            if ($request !== null && $totpEnabled && !$requireOrigin($request)) {
                throw new TotpException('origin_rejected');
            }

            if ($request !== null) {
                $response = $this->totp()->closePresentedChallenge($request, $response, !$totpEnabled);
            }

            $result = $this->users()->withLockedUser(
                (int) $user['id'],
                function (?array $current) use ($user, $request, $rememberMe, $totpEnabled): array {
                    if (
                        $current === null
                        || $current['pwdhash'] !== $user['pwdhash']
                        || $current['email'] !== $user['email']
                    ) {
                        throw new AuthException('Invalid email or password', 401);
                    }

                    $this->requireLoginEligible($current);
                    if (TotpService::enabled($current) !== $totpEnabled || $current['auth_version'] !== $user['auth_version']) {
                        throw new AuthException('Invalid email or password', 401);
                    }

                    if ($totpEnabled) {
                        return $request === null
                            ? ['authenticated' => false, 'next' => 'totp_required']
                            : $this->totp()->startLogin($current, $rememberMe);
                    }

                    return ['user' => $current, 'session' => $this->createLoginSession($current, $rememberMe)];
                }
            );

            if (isset($result['session'])) {
                return $this->completeLogin($result['user'], $result['session'], $response, $rememberMe);
            }

            if (isset($result['credential'])) {
                $response = $this->totp()->setChallengeCookie($response, $result['credential']);
                unset($result['credential']);
            }

            return $result + ['response' => $response];
        } catch (AuthException $exception) {
            throw $exception;
        } catch (RuntimeException | ContainerExceptionInterface) {
            throw new TotpException('auth_unavailable');
        }
    }

    /** @param array<string, mixed> $user */
    private function requireLoginEligible(array $user): void
    {
        if (!$user['email_verified']) {
            throw new AuthException('Email address is not verified', 403);
        }

        if (!$user['enabled']) {
            throw new AuthException('User is disabled', 403);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array{refresh: RefreshToken, access: AccessToken}
     */
    private function createLoginSession(array $user, bool $rememberMe): array
    {
        // Reject missing cookie configuration before writing a refresh record.
        $this->loginCookieSettings($rememberMe);
        $handler = new TokenHandler($this->container);
        $handler->generateRefreshToken((string) $user['id'], $this->userTokenClaims($user));
        $refreshToken = $handler->getRefreshToken();
        $accessToken = $refreshToken->getAccessToken();
        $this->storeRefreshTokenForUser((int) $user['id'], $refreshToken);

        return ['refresh' => $refreshToken, 'access' => $accessToken];
    }

    /**
     * @param array<string, mixed> $user
     * @param array{refresh: RefreshToken, access: AccessToken} $session
     * @return array<string, mixed>
     */
    private function completeLogin(array $user, array $session, ResponseInterface $response, bool $rememberMe): array
    {
        $response = $this->setLoginCookie(
            $response,
            $session['refresh']->getTokenJWT(),
            $session['access']->getTokenJWT(),
            $rememberMe
        );
        $this->logTokenIssued($user, (string) $session['refresh']->getToken()->jti);

        return ['authenticated' => true, 'response' => $response] + $this->safeUserFields($user);
    }

    /** @return array<string, mixed> */
    public function loginTotp(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $flowId,
        #[SensitiveParameter]
        string $code
    ): array {
        $result = $this->totp()->completeLogin($request, $flowId, $code, $this->createLoginSession(...));
        $response = $this->totp()->clearChallengeCookie($response);

        try {
            return $this->completeLogin($result['user'], $result['session'], $response, $result['rememberMe']);
        } catch (RuntimeException | InvalidArgumentException) {
            throw new TotpException('auth_unavailable');
        }
    }

    /** @return array<string, mixed> */
    public function totpStatus(CurrentSession $session, ResponseInterface $response): array
    {
        return $this->totp()->status($session) + ['response' => $response];
    }

    /** @return array<string, mixed> */
    public function startTotpSetup(
        CurrentSession $session,
        ResponseInterface $response,
        #[SensitiveParameter]
        string $currentPassword,
        #[SensitiveParameter]
        ?string $code
    ): array {
        return $this->totp()->setup($session, $currentPassword, $code) + ['response' => $response];
    }

    /** @return array<string, mixed> */
    public function confirmTotpSetup(
        CurrentSession $session,
        ResponseInterface $response,
        string $flowId,
        #[SensitiveParameter]
        string $code
    ): array {
        $result = $this->totp()->confirmSetup($session, $flowId, $code);

        return $result + ['response' => $this->clearLoginCookie($response)];
    }

    /** @return array<string, mixed> */
    public function disableTotp(
        CurrentSession $session,
        ResponseInterface $response,
        #[SensitiveParameter]
        string $currentPassword,
        #[SensitiveParameter]
        string $code
    ): array {
        $result = $this->totp()->disable($session, $currentPassword, $code);

        return $result + ['response' => $this->clearLoginCookie($response)];
    }

    /** @return array<string, mixed> */
    public function startTotpRecovery(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $flowId,
        #[SensitiveParameter]
        string $recoveryCode
    ): array {
        $result = $this->totp()->recover($request, $flowId, $recoveryCode);
        $response = $this->totp()->setChallengeCookie($response, $result['credential'], true);
        unset($result['credential']);

        return $result + ['response' => $response];
    }

    /** @return array<string, mixed> */
    public function confirmTotpRecovery(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $flowId,
        #[SensitiveParameter]
        string $code
    ): array {
        $result = $this->totp()->confirmRecovery($request, $flowId, $code);
        $response = $this->totp()->clearChallengeCookie($response);

        return $result + ['response' => $this->clearLoginCookie($response)];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(ServerRequestInterface $request, ResponseInterface $response): array
    {
        try {
            $bundle = $this->readCookieBundle($request);
            $accessToken = new AccessToken($this->container);
            $accessToken->loadAccessTokenFromJWT($bundle['accessToken']);
            $accessClaims = $accessToken->getToken();

            if ($this->statelessSessions()) {
                return [
                    'authenticated' => true,
                    'response' => $response,
                ] + $this->safeUserFieldsFromClaims($accessClaims);
            }

            $refreshContext = $this->validRefreshContext($bundle['refreshToken']);
            if ((string) $accessClaims->sub !== (string) $refreshContext['user']['id']) {
                return $this->unauthenticated($response, true);
            }

            return ['authenticated' => true, 'response' => $response] + $this->safeUserFields($refreshContext['user']);
        } catch (ExpiredException) {
            return $this->refreshExpiredAccessToken($request, $response);
        } catch (Throwable) {
            return $this->unauthenticated($response, $this->requestHasLoginCookie($request));
        }
    }

    /**
     * Checks the current refresh record and account without issuing a cookie or rotating a token.
     */
    public function currentSession(ServerRequestInterface $request): CurrentSession
    {
        try {
            $bundle = $this->readCookieBundle($request);
            $context = $this->decodeRefreshContext($bundle['refreshToken']);
        } catch (Throwable) {
            throw new TotpException('current_session_required');
        }

        try {
            $this->requireStoredRefreshSession($context['session']);
            $this->requireActiveRefreshUser($this->users()->findUserById($context['session']->userId));
        } catch (AuthException) {
            throw new TotpException('current_session_required');
        } catch (RuntimeException | ContainerExceptionInterface) {
            throw new TotpException('auth_unavailable');
        }

        return $context['session'];
    }

    /**
     * @return array<string, mixed>
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $response = $this->totp()->closePresentedChallenge($request, $response);

        try {
            $bundle = $this->readCookieBundle($request);
            $session = $this->decodeRefreshContext($bundle['refreshToken'])['session'];
        } catch (Throwable) {
            // Logout must be idempotent for missing or invalid cookies.
            return $this->unauthenticated($this->clearLoginCookie($response));
        }

        $this->users()->withLockedUser($session->userId, function (?array $user) use ($session): void {
            if ($user !== null) {
                $this->users()->revokeRefreshTokenByJti($session->refreshJti, $this->now());
            }
        });

        return $this->unauthenticated($this->clearLoginCookie($response));
    }

    /**
     * @return array<string, mixed>
     */
    public function changePassword(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $currentPassword,
        string $newPassword,
        string $confirmPassword
    ): array {
        try {
            $bundle = $this->readCookieBundle($request);
            $refreshContext = $this->validRefreshContext($bundle['refreshToken']);
        } catch (Throwable) {
            throw new AuthException('Authentication required', 401);
        }

        $user = $refreshContext['user'];
        if (!is_string($user['pwdhash']) || !password_verify($currentPassword, $user['pwdhash'])) {
            throw new InvalidArgumentException('Current password is incorrect');
        }

        $this->assertValidNewPassword($newPassword, $confirmPassword, $user['pwdhash']);
        $session = $refreshContext['session'];
        $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->users()->withLockedUser(
            $session->userId,
            function (?array $current) use ($session, $user, $newPasswordHash): void {
                $this->requireActiveRefreshUser($current);
                $this->requireStoredRefreshSession($session, true);
                if ($current['pwdhash'] !== $user['pwdhash']) {
                    throw new InvalidArgumentException('Current password is incorrect');
                }

                $this->users()->changePasswordAndRevokeOtherRefreshTokens(
                    $session->userId,
                    $newPasswordHash,
                    $session->refreshJti,
                    $this->now()
                );
            }
        );
        $updatedUser = $this->users()->findUserById((int) $user['id']) ?? $user;

        $this->logger->notice('auth.password_changed', [
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'refresh_token_jti' => $session->refreshJti,
        ]);

        return [
            'changed' => true,
            'authenticated' => true,
            'response' => $response,
        ] + $this->safeUserFields($updatedUser);
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshExpiredAccessToken(ServerRequestInterface $request, ResponseInterface $response): array
    {
        try {
            $bundle = $this->readCookieBundle($request);
            $context = $this->decodeRefreshContext($bundle['refreshToken']);
            $session = $context['session'];
            $stateless = $this->statelessSessions();
            $result = $this->users()->withLockedUser(
                $session->userId,
                function (?array $user) use ($context, $session, $stateless): array {
                    $this->requireStoredRefreshSession($session, true);
                    $this->requireActiveRefreshUser($user);

                    $refresh = $context['refreshToken'];
                    if ($stateless && $this->shouldAutoRefreshRefreshToken($refresh->getToken())) {
                        $refresh = $this->rotateRefreshToken($refresh, $session);
                    }

                    return ['refresh' => $refresh, 'access' => $refresh->getAccessToken(), 'user' => $user];
                }
            );
            $response = $this->setLoginCookie(
                $response,
                $result['refresh']->getTokenJWT(),
                $result['access']->getTokenJWT(),
                $bundle['rememberMe']
            );
            $claims = $result['refresh']->getToken();
            if ($stateless) {
                $this->logTokenIssuedFromClaims($claims, 'access_refresh');

                return ['authenticated' => true, 'response' => $response] + $this->safeUserFieldsFromClaims($claims);
            }

            $this->logTokenIssued($result['user'], (string) $claims->jti, 'access_refresh');

            return ['authenticated' => true, 'response' => $response] + $this->safeUserFields($result['user']);
        } catch (Throwable $throwable) {
            $this->logger->warning('auth.refresh_token_rejected', [
                'reason' => $throwable instanceof AuthException ? $throwable->getMessage() : 'Refresh token could not be validated',
                'exception' => $throwable::class,
            ]);

            return $this->unauthenticated($response, true);
        }
    }

    private function rotateRefreshToken(RefreshToken $refresh, CurrentSession $session): RefreshToken
    {
        $claims = $refresh->getToken();
        $handler = new TokenHandler($this->container);
        $handler->generateRefreshToken((string) $claims->sub, $this->customClaims($claims));
        $successor = $handler->getRefreshToken();
        $this->storeRefreshTokenForUser($session->userId, $successor);
        if (!$this->users()->revokeRefreshTokenByJti($session->refreshJti, $this->now())) {
            throw new AuthException('Refresh token is not active');
        }

        return $successor;
    }

    /**
     * @return array{refreshToken: RefreshToken, session: CurrentSession, user: array<string, mixed>}
     */
    private function validRefreshContext(#[SensitiveParameter] string $refreshJwt): array
    {
        $context = $this->decodeRefreshContext($refreshJwt);
        $this->requireStoredRefreshSession($context['session']);
        $user = $this->users()->findUserById($context['session']->userId);
        $this->requireActiveRefreshUser($user);

        return $context + ['user' => $user];
    }

    /** @return array{refreshToken: RefreshToken, session: CurrentSession} */
    private function decodeRefreshContext(#[SensitiveParameter] string $refreshJwt): array
    {
        $refreshToken = new RefreshToken($this->container);
        $refreshToken->loadRefreshTokenFromJWT($refreshJwt);
        $claims = $refreshToken->getToken();

        $userId = (int) $claims->sub;
        if ((string) $userId !== (string) $claims->sub || $userId <= 0) {
            throw new AuthException('Refresh token subject is invalid');
        }

        return [
            'refreshToken' => $refreshToken,
            'session' => new CurrentSession($userId, (string) $claims->jti, $this->tokenHash($refreshJwt)),
        ];
    }

    private function requireStoredRefreshSession(CurrentSession $session, bool $forUpdate = false): void
    {
        $now = $this->now();
        $stored = $forUpdate
            ? $this->users()->findValidRefreshTokenForUpdate($session->refreshJti, $session->refreshTokenHash(), $now)
            : $this->users()->findValidRefreshToken($session->refreshJti, $session->refreshTokenHash(), $now);
        if ($stored === null) {
            throw new AuthException('Refresh token is not active');
        }

        if ((int) $stored['users_id'] !== $session->userId) {
            throw new AuthException('Refresh token subject does not match stored user');
        }
    }

    /** @param array<string, mixed>|null $user */
    private function requireActiveRefreshUser(?array $user): void
    {
        if ($user === null || !$user['enabled'] || !$user['email_verified']) {
            throw new AuthException('Refresh token user is not active');
        }
    }

    /**
     * @return array{refreshToken: string, accessToken: string, rememberMe: bool}
     */
    private function readCookieBundle(ServerRequestInterface $request): array
    {
        $cookie = $this->loginCookieSettings();
        $payload = EncryptedCookies::decryptCookie(
            $request,
            $cookie['name'],
            $cookie['key'],
            $cookie['salt']
        );
        $bundle = json_decode($payload, true, 2, JSON_THROW_ON_ERROR);
        if (
            !is_array($bundle)
            || !isset($bundle['refreshToken'], $bundle['accessToken'])
            || !is_string($bundle['refreshToken'])
            || !is_string($bundle['accessToken'])
            || $bundle['refreshToken'] === ''
            || $bundle['accessToken'] === ''
        ) {
            throw new RuntimeException('Login cookie payload is invalid');
        }

        return [
            'refreshToken' => $bundle['refreshToken'],
            'accessToken' => $bundle['accessToken'],
            'rememberMe' => ($bundle['rememberMe'] ?? false) === true,
        ];
    }

    private function setLoginCookie(
        ResponseInterface $response,
        string $refreshJwt,
        string $accessJwt,
        bool $persistent = false
    ): ResponseInterface
    {
        $cookie = $this->loginCookieSettings($persistent);
        $payload = json_encode([
            'refreshToken' => $refreshJwt,
            'accessToken' => $accessJwt,
            'rememberMe' => $persistent,
        ], JSON_THROW_ON_ERROR);
        if (!is_string($payload)) {
            throw new JsonException('Failed to encode login cookie payload');
        }

        return EncryptedCookies::setEncryptedCookie(
            $response,
            $cookie['name'],
            $payload,
            $cookie['key'],
            $cookie['salt'],
            $cookie['options']
        );
    }

    private function clearLoginCookie(ResponseInterface $response): ResponseInterface
    {
        $cookie = $this->loginCookieSettings();

        return EncryptedCookies::unsetEncryptedCookie($response, $cookie['name'], $cookie['options']);
    }

    private function requestHasLoginCookie(ServerRequestInterface $request): bool
    {
        try {
            $cookie = $this->loginCookieSettings();
        } catch (Throwable) {
            return false;
        }

        $value = $request->getCookieParams()[$cookie['name']] ?? null;

        return is_string($value) && $value !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function unauthenticated(ResponseInterface $response, bool $clearCookie = false): array
    {
        if ($clearCookie) {
            $response = $this->clearLoginCookie($response);
        }

        return [
            'authenticated' => false,
            'response' => $response,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function safeUserFields(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'enabled' => (bool) $user['enabled'],
            'emailVerified' => (bool) $user['email_verified'],
            'nickname' => $user['nickname'],
            'name' => $user['name'],
            'surname' => $user['surname'],
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function userTokenClaims(array $user): array
    {
        return [
            'email' => (string) $user['email'],
            'enabled' => (bool) $user['enabled'],
            'emailVerified' => (bool) $user['email_verified'],
            'nickname' => $user['nickname'],
            'name' => $user['name'],
            'surname' => $user['surname'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeUserFieldsFromClaims(\stdClass $claims): array
    {
        return [
            'id' => (int) $claims->sub,
            'email' => (string) ($claims->email ?? ''),
            'enabled' => (bool) ($claims->enabled ?? true),
            'emailVerified' => (bool) ($claims->emailVerified ?? false),
            'nickname' => isset($claims->nickname) ? (string) $claims->nickname : null,
            'name' => isset($claims->name) ? (string) $claims->name : null,
            'surname' => isset($claims->surname) ? (string) $claims->surname : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customClaims(\stdClass $claims): array
    {
        $reservedClaims = array_flip(['iss', 'aud', 'sub', 'iat', 'nbf', 'exp', 'jti']);

        return array_diff_key((array) $claims, $reservedClaims);
    }

    private function statelessSessions(): bool
    {
        $auth = $this->authSettings();
        $mode = $auth['sessionMode'] ?? $auth['mode'] ?? null;

        if (is_string($mode)) {
            return strtolower(trim($mode)) === 'stateless';
        }

        if (array_key_exists('validateRefreshTokenOnEveryRequest', $auth)) {
            return ($auth['validateRefreshTokenOnEveryRequest'] ?? true) === false;
        }

        return true;
    }

    private function storeRefreshTokenForUser(int $userId, RefreshToken $refreshToken): void
    {
        $claims = $refreshToken->getToken();
        $this->users()->storeRefreshToken(
            $userId,
            (string) $claims->jti,
            $this->tokenHash($refreshToken->getTokenJWT()),
            $this->dateFromUnix((int) $claims->iat),
            $this->dateFromUnix((int) $claims->exp)
        );
    }

    private function shouldAutoRefreshRefreshToken(\stdClass $refreshClaims): bool
    {
        $settings = $this->settings();
        $refreshSettings = $settings['jwt_refresh_token'] ?? [];
        if (!is_array($refreshSettings)) {
            return false;
        }

        $autoRefresh = $refreshSettings['auto_refresh'] ?? $refreshSettings['autoRefresh'] ?? 0;
        if (!is_int($autoRefresh) && !(is_string($autoRefresh) && ctype_digit($autoRefresh))) {
            return false;
        }

        $autoRefreshSeconds = (int) $autoRefresh;
        if ($autoRefreshSeconds <= 0) {
            return false;
        }

        $secondsToExpire = (int) $refreshClaims->exp - time();

        return $secondsToExpire > 0 && ($secondsToExpire - $autoRefreshSeconds) < 0;
    }

    private function assertValidPassword(string $password): void
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password is required');
        }

        if (strlen($password) > 72) {
            throw new InvalidArgumentException('Password must be 72 bytes or shorter');
        }
    }

    private function assertValidNewPassword(string $newPassword, string $confirmPassword, string $currentPasswordHash): void
    {
        $this->assertValidPassword($newPassword);

        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('New password must be at least 8 characters');
        }

        if ($newPassword !== $confirmPassword) {
            throw new InvalidArgumentException('Password confirmation does not match');
        }

        if (password_verify($newPassword, $currentPasswordHash)) {
            throw new InvalidArgumentException('New password must be different from current password');
        }
    }

    private function users(): UserRepository
    {
        if (!$this->users instanceof UserRepository) {
            $this->users = $this->container->get(UserRepository::class);
        }

        return $this->users;
    }

    private function totp(): TotpService
    {
        if (!$this->totp instanceof TotpService) {
            $clock = $this->container->has(ClockInterface::class)
                ? $this->container->get(ClockInterface::class)
                : new SystemClock();
            $this->totp = new TotpService($this->users(...), $this->logger, $clock, $this->settings());
        }

        return $this->totp;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function dateFromNow(int $ttl): string
    {
        return $this->dateFromUnix(time() + $ttl);
    }

    private function dateFromUnix(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function emailVerificationTtl(): int
    {
        $settings = $this->settings();
        $emailSettings = $settings['emailVerification'] ?? [];
        if (!is_array($emailSettings)) {
            $emailSettings = [];
        }
        $ttl = $emailSettings['expire'] ?? $emailSettings['ttl'] ?? 86400;

        return $this->positiveInteger($ttl, 'emailVerification.expire');
    }

    private function devExposeVerificationToken(): bool
    {
        $settings = $this->settings();
        $emailSettings = $settings['emailVerification'] ?? [];

        return is_array($emailSettings) && ($emailSettings['devExposeToken'] ?? false) === true;
    }

    /**
     * @return array{name: string, key: string, salt: string, options: array<string, mixed>}
     */
    private function loginCookieSettings(bool $persistent = true): array
    {
        $settings = $this->settings();
        $cookie = $settings['login_cookie'] ?? [];
        if (!is_array($cookie)) {
            throw new RuntimeException('Missing login_cookie settings');
        }

        $name = $cookie['cookiename'] ?? $cookie['name'] ?? null;
        $key = $cookie['key'] ?? $cookie['secret'] ?? null;
        $salt = $cookie['salt'] ?? null;
        if (!is_string($name) || $name === '' || !is_string($key) || $key === '' || !is_string($salt) || $salt === '') {
            throw new RuntimeException('Login cookie name, key, and salt are required');
        }

        $options = [];
        if (isset($cookie['cookieopts']) && is_array($cookie['cookieopts'])) {
            $options = $cookie['cookieopts'];
        } else {
            foreach (['path', 'domain', 'hostonly', 'secure', 'httponly', 'httpOnly', 'samesite', 'sameSite'] as $nameKey) {
                if (array_key_exists($nameKey, $cookie)) {
                    $options[$nameKey] = $cookie[$nameKey];
                }
            }
        }

        $ttl = $persistent ? $this->persistentCookieTtl($settings, $cookie) : null;
        if ($ttl !== null) {
            $options['expires'] = time() + $this->positiveInteger($ttl, 'login_cookie.ttl');
        }

        return [
            'name' => $name,
            'key' => $key,
            'salt' => $salt,
            'options' => $options,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $cookie
     */
    private function persistentCookieTtl(array $settings, array $cookie): mixed
    {
        if (array_key_exists('ttl', $cookie)) {
            return $cookie['ttl'];
        }

        $refreshToken = $settings['jwt_refresh_token'] ?? [];
        if (!is_array($refreshToken)) {
            return null;
        }

        $ttl = $refreshToken['expire'] ?? $refreshToken['ttl'] ?? null;
        $timeSkew = $refreshToken['time_skew'] ?? 0;
        if ((is_int($ttl) || (is_string($ttl) && ctype_digit($ttl)))
            && (is_int($timeSkew) || (is_string($timeSkew) && ctype_digit($timeSkew)))
        ) {
            return (int) $ttl + (int) $timeSkew;
        }

        return $ttl;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = $this->container->get('settings');
        if (!is_array($settings)) {
            throw new RuntimeException('Application settings are unavailable');
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function authSettings(): array
    {
        $settings = $this->settings();
        $auth = $settings['auth'] ?? [];

        return is_array($auth) ? $auth : [];
    }

    private function positiveInteger(mixed $value, string $name): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new RuntimeException("$name must be an integer");
        }

        $integer = (int) $value;
        if ($integer <= 0) {
            throw new RuntimeException("$name must be positive");
        }

        return $integer;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function logTokenIssued(array $user, string $refreshTokenJti, string $reason = 'login'): void
    {
        $this->logger->notice('auth.token_issued', [
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'refresh_token_jti' => $refreshTokenJti,
            'reason' => $reason,
        ]);
    }

    private function logTokenIssuedFromClaims(\stdClass $claims, string $reason): void
    {
        $this->logger->notice('auth.token_issued', [
            'user_id' => (int) $claims->sub,
            'email' => (string) ($claims->email ?? ''),
            'refresh_token_jti' => (string) $claims->jti,
            'reason' => $reason,
        ]);
    }
}
