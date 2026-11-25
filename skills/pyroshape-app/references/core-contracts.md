# Core contracts

Confirm version-specific behavior in the installed package or the checkout being changed.

## Bootstrap and routing

- `src/App.php` initializes PHP-DI from the app's configuration and registers the Core middleware and routes. `basePath` is empty for root deployments and takes a prefix without a trailing slash. `frontend.appPath` points to the static SPA build. Preserve the `setupAppRoutes()` hook between Core routes and SPA routes. Missing `/api` or `/health` paths are handled as HTTP errors instead of receiving `index.html`.
- Check `src/Middlewares/` for request order, `src/Exceptions/JsonErrorHandler.php` for HTTP error payloads, and `src/Logger/` for the separate application and access loggers. For bootstrap regressions, use `tests/app_routes_before_spa_fallback.php` or `tests/container_cache_prune.php`, depending on the affected behavior.

## Authentication

- Start with `src/Controllers/AuthController.php` for payloads and status codes, `src/Auth/AuthService.php` for session behavior, `src/Http/EncryptedCookies.php` for cookies, and `src/Auth/UserRepository.php` for persistence. Core provides `GET /api/session`, `GET /api/auth/verify-email`, and `POST` routes at `/api/auth/register`, `/api/auth/login`, `/api/auth/logout`, and `/api/auth/change-password`.
- The current default accepts a valid access JWT without consulting the database on every request. Refresh tokens still have persisted hashes, are checked during refresh, and are subject to revocation. A revoked refresh token does not by itself invalidate an unexpired access JWT in stateless mode. Preserve the configured mode and propagate session `Set-Cookie` headers from middleware to the outgoing response.
- If login or refresh fails, inspect the app and access logs, confirm the auth schema and PDO driver, then check the active `jwt_access_token`, `jwt_refresh_token`, and `login_cookie` settings against the installed Core. Use suitable local keys and check cookie flags, path, and origin when the browser fails to send the cookie. Keep secret values out of logs and examples.
- Focus on the relevant regression script. Core has `tests/auth_stateless_session.php`, `tests/auth_password_change.php`, and `tests/auth_controller_logging.php`. The session and password scripts use SQLite fixtures and require `pdo_sqlite`.

## PDO and migrations

- Core's `database/migrations/001_auth_schema.sql` is a MySQL baseline. Existing apps can supply their own migrations and SQL dialect. Check the app's DSN and schema before changing a query or applying a migration. Preserve the parameter and value types expected by the target driver, especially boolean flags on PostgreSQL.
- `php tests/postgres_user_repository.php` exercises the PostgreSQL repository with a temporary table when `PYROSHAPE_TEST_POSTGRES_DSN` points to a test database and `pdo_pgsql` is available. Supply `PYROSHAPE_TEST_POSTGRES_USER` and `PYROSHAPE_TEST_POSTGRES_PASSWORD` if needed. The script reports a skip without a DSN.
