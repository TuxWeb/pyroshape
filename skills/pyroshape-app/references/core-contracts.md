# Core contracts

Confirm version-specific behavior in the installed package or the checkout being changed.

## Bootstrap and routing

- `src/App.php` initializes PHP-DI from the app's configuration and registers the Core middleware and routes. `basePath` is empty for root deployments and takes a prefix without a trailing slash. `frontend.appPath` points to the static SPA build. Preserve the `setupAppRoutes()` hook between Core routes and SPA routes. Missing `/api` or `/health` paths are handled as HTTP errors instead of receiving `index.html`.
- Check `src/Middlewares/` for request order, `src/Exceptions/JsonErrorHandler.php` for HTTP error payloads, and `src/Logger/` for the separate application and access loggers. For bootstrap regressions, use `tests/app_routes_before_spa_fallback.php` or `tests/container_cache_prune.php`, depending on the affected behavior.

## Authentication

- Start with `src/Controllers/AuthController.php` for payloads and status codes, `src/Auth/AuthService.php` for session behavior, `src/Http/EncryptedCookies.php` for cookies, and `src/Auth/UserRepository.php` for persistence. Core provides `GET /api/session`, `GET /api/auth/verify-email`, and `POST` routes at `/api/auth/register`, `/api/auth/login`, `/api/auth/logout`, and `/api/auth/change-password`.
- If login or refresh fails, inspect the app and access logs, confirm the auth schema and PDO driver, then check the active `jwt_access_token`, `jwt_refresh_token`, and `login_cookie` settings against the installed Core. Use suitable local keys and check cookie flags, path, and origin when the browser fails to send the cookie. Keep secret values out of logs and examples.
- Focus on the relevant regression script. Core has `tests/auth_stateless_session.php`, `tests/auth_password_change.php`, and `tests/auth_controller_logging.php`. The session and password scripts use SQLite fixtures and require `pdo_sqlite`.

### Stateless sessions and forced logout

- Keep the default stateless session mode for ordinary requests. Core stores two signed JWTs in the encrypted login cookie. The starter uses `HttpOnly`, `SameSite=Lax`, and `Secure` in production. Keep the JWTs in the cookie and propagate session `Set-Cookie` headers from middleware to the outgoing response.
- The short-lived access JWT authenticates ordinary requests using its verified claims. Those claims reflect the account at issuance, so check current permissions separately wherever freshness matters. The longer-lived refresh JWT can renew access only after Core validates its hash and identifier against the active database record and checks the current account status.
- Logout expires the browser cookie and revokes its valid refresh token. An already-issued access JWT remains acceptable on ordinary routes until its effective expiry, including any configured clock skew. Base the app's forced-logout policy on that window. Choose a short access-token lifetime and define which operations must stop immediately and whether revocation targets one session or the whole account.
- Before a sensitive operation such as changing credentials or permissions, exporting private data, or deleting records, require a current server-side check of the applicable session or account revocation state, permissions, and ownership. Use the access JWT as identity input only for this decision. Require recent reauthentication where warranted and deny the operation if the required state is unavailable or revoked. Core's password change illustrates the pattern by checking the active refresh session and the current password.
- Test the policy with a revoked session while its access JWT is still valid. Ordinary routes should follow the stateless policy, a revoked refresh token must fail renewal, and sensitive operations must reject the session immediately.
- On Pyroshape 0.10.0, use `RequireCurrentSession` on sensitive routes that require a live refresh record and the current account. It places a `CurrentSession` in `SESSION_ATTRIBUTE` and performs no cookie renewal. Enforce current permissions and ownership separately. For TOTP, treat a `202` login response as pending and load the normal session only after `authenticated: true`. Factor changes revoke all refresh sessions for the account. [TOTP API](../../../docs/specs/totp-authentication.md#http-contract) · [Migration and rollout](../../../docs/releases/v0.10.0.md)

## PDO and migrations

- Core's `database/migrations/001_auth_schema.sql` is a MySQL baseline. Existing apps can supply their own migrations and SQL dialect. Check the app's DSN and schema before changing a query or applying a migration. Preserve the parameter and value types expected by the target driver, especially boolean flags on PostgreSQL.
- `php tests/postgres_user_repository.php` exercises the PostgreSQL repository with a temporary table when `PYROSHAPE_TEST_POSTGRES_DSN` points to a test database and `pdo_pgsql` is available. Supply `PYROSHAPE_TEST_POSTGRES_USER` and `PYROSHAPE_TEST_POSTGRES_PASSWORD` if needed. The script reports a skip without a DSN.
