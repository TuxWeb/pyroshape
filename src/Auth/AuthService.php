<?php

declare(strict_types=1);

namespace Dam2k\Pyroshape\Core\Auth;

use Dam2k\Pyroshape\Core\Auth\Tokens\AccessToken;
use Dam2k\Pyroshape\Core\Auth\Tokens\RefreshToken;
use Dam2k\Pyroshape\Core\Auth\Tokens\TokenHandler;
use Dam2k\Pyroshape\Core\Http\EncryptedCookies;
use Firebase\JWT\ExpiredException;
use InvalidArgumentException;
use JsonException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

class AuthService
{
    private const DUMMY_BCRYPT_HASH = '$2y$12$bNmI7bFzmrJZM12sQOMC5eNZ0BBFxS0.XdKyRcKLbC8vcLMGHWkPq';

    private ?UserRepository $users;
    private LoggerInterface $logger;

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
     * @return array<string, mixed>
     */
    public function login(string $email, string $password, ResponseInterface $response, bool $rememberMe = false): array
    {
        $this->assertValidPassword($password);

        $user = $this->users()->findUserByEmail($email);
        $passwordHash = is_array($user) && is_string($user['pwdhash']) ? $user['pwdhash'] : self::DUMMY_BCRYPT_HASH;
        $validPassword = password_verify($password, $passwordHash);
        if ($user === null || !is_string($user['pwdhash']) || !$validPassword) {
            throw new AuthException('Invalid email or password', 401);
        }

        if (!$user['email_verified']) {
            throw new AuthException('Email address is not verified', 403);
        }

        if (!$user['enabled']) {
            throw new AuthException('User is disabled', 403);
        }

        $handler = new TokenHandler($this->container);
        $handler->generateRefreshToken((string) $user['id'], $this->userTokenClaims($user));
        $refreshToken = $handler->getRefreshToken();
        $accessToken = $refreshToken->getAccessToken();
        $refreshClaims = $refreshToken->getToken();

        $this->storeRefreshTokenForUser((int) $user['id'], $refreshToken);

        $response = $this->setLoginCookie($response, $refreshToken->getTokenJWT(), $accessToken->getTokenJWT(), $rememberMe);
        $this->logTokenIssued($user, (string) $refreshClaims->jti);

        return ['authenticated' => true, 'response' => $response] + $this->safeUserFields($user);
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
     * @return array<string, mixed>
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): array
    {
        try {
            $bundle = $this->readCookieBundle($request);
            $refreshToken = new RefreshToken($this->container);
            $refreshToken->loadRefreshTokenFromJWT($bundle['refreshToken']);
            $claims = $refreshToken->getToken();
        } catch (Throwable) {
            // Logout must be idempotent for missing or invalid cookies.
            return $this->unauthenticated($this->clearLoginCookie($response));
        }

        $this->users()->revokeRefreshTokenByJti((string) $claims->jti, $this->now());

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
        $refreshJti = (string) $refreshContext['refreshToken']->getToken()->jti;
        $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->users()->changePasswordAndRevokeOtherRefreshTokens(
            (int) $user['id'],
            $newPasswordHash,
            $refreshJti,
            $this->now()
        );
        $updatedUser = $this->users()->findUserById((int) $user['id']) ?? $user;

        $this->logger->notice('auth.password_changed', [
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'refresh_token_jti' => $refreshJti,
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
            if ($this->statelessSessions()) {
                $refreshContext = $this->validRefreshContext($bundle['refreshToken']);
                $refreshToken = $refreshContext['refreshToken'];
                $refreshClaims = $refreshToken->getToken();
                $refreshTokenJwt = $bundle['refreshToken'];
                $rememberMe = $bundle['rememberMe'];

                if ($this->shouldAutoRefreshRefreshToken($refreshClaims)) {
                    $oldRefreshJti = (string) $refreshClaims->jti;
                    $refreshed = new TokenHandler($this->container);
                    $refreshed->generateRefreshToken(
                        (string) $refreshClaims->sub,
                        $this->customClaims($refreshClaims)
                    );
                    $refreshToken = $refreshed->getRefreshToken();
                    $refreshTokenJwt = $refreshToken->getTokenJWT();
                    $refreshClaims = $refreshToken->getToken();
                    $this->storeRefreshTokenForUser((int) $refreshContext['user']['id'], $refreshToken);
                    $this->users()->revokeRefreshTokenByJti($oldRefreshJti, $this->now());
                }

                $accessToken = $refreshToken->getAccessToken();
                $response = $this->setLoginCookie(
                    $response,
                    $refreshTokenJwt,
                    $accessToken->getTokenJWT(),
                    $rememberMe
                );
                $this->logTokenIssuedFromClaims($refreshClaims, 'access_refresh');

                return [
                    'authenticated' => true,
                    'response' => $response,
                ] + $this->safeUserFieldsFromClaims($refreshClaims);
            }

            $refreshContext = $this->validRefreshContext($bundle['refreshToken']);
            $accessToken = $refreshContext['refreshToken']->getAccessToken();
            $response = $this->setLoginCookie(
                $response,
                $bundle['refreshToken'],
                $accessToken->getTokenJWT(),
                $bundle['rememberMe']
            );
            $this->logTokenIssued(
                $refreshContext['user'],
                (string) $refreshContext['refreshToken']->getToken()->jti,
                'access_refresh'
            );

            return ['authenticated' => true, 'response' => $response] + $this->safeUserFields($refreshContext['user']);
        } catch (Throwable $throwable) {
            $this->logger->warning('auth.refresh_token_rejected', [
                'reason' => $throwable->getMessage(),
                'exception' => $throwable::class,
            ]);

            return $this->unauthenticated($response, true);
        }
    }

    /**
     * @return array{refreshToken: RefreshToken, user: array<string, mixed>}
     */
    private function validRefreshContext(string $refreshJwt): array
    {
        $refreshToken = new RefreshToken($this->container);
        $refreshToken->loadRefreshTokenFromJWT($refreshJwt);
        $claims = $refreshToken->getToken();
        $storedToken = $this->users()->findValidRefreshToken(
            (string) $claims->jti,
            $this->tokenHash($refreshJwt),
            $this->now()
        );
        if ($storedToken === null) {
            throw new RuntimeException('Refresh token is not active');
        }

        $user = $this->users()->findUserById((int) $storedToken['users_id']);
        if ($user === null || !$user['enabled'] || !$user['email_verified']) {
            throw new RuntimeException('Refresh token user is not active');
        }

        if ((string) $claims->sub !== (string) $user['id']) {
            throw new RuntimeException('Refresh token subject does not match stored user');
        }

        return ['refreshToken' => $refreshToken, 'user' => $user];
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
