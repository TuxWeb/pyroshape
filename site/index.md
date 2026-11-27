# Pyroshape

Pyroshape is TuxWeb's MIT-licensed PHP backend for single-page applications. It can serve a compiled frontend and a JSON API from the same origin. Slim handles routing and PHP-DI wires services together using standard PHP interfaces. Core also provides logging, JSON error handling, and PDO access. Use it for customer portals, internal tools, or dashboards.

## Start a project

- For an existing PHP 8.5+ app, run `composer require tuxweb-it/pyroshape`. Extend the Core `App` class and register JSON endpoints in `setupAppRoutes()`.
- For a new app, follow the [starter guide](https://github.com/TuxWeb/pyroshape/tree/main/apps/skeleton). The starter includes SvelteKit, TypeScript, shadcn-svelte, and PostgreSQL setup. MariaDB is also supported. You can use a different frontend with the same API.
- In Codex or Claude Code, start with your app idea. Ask the agent to use the Pyroshape starter, follow the [project instructions](https://github.com/TuxWeb/pyroshape/blob/main/AGENTS.md), and get the first screen running locally.

## Authentication and sessions

Core exposes JSON endpoints for registration, email verification, sign-in, session status, password changes, and logout. The encrypted session cookie holds a short-lived access JWT and a refresh JWT. Ordinary requests can validate the access token without a session database lookup. Renewal checks the server-side session and rotates the refresh token. Forced logout blocks renewal for the affected session. An already-issued access token can remain valid until it expires. For sensitive operations, check current session status, permissions, and ownership in the application. See the [Core session contract](https://github.com/TuxWeb/pyroshape/blob/main/skills/pyroshape-app/references/core-contracts.md#stateless-sessions-and-forced-logout).

## Project links

- [Bilingual showcase](https://pyroshape.tuxweb.it/)
- [Italian showcase](https://pyroshape.tuxweb.it/?lang=it)
- [Source, documentation, and issues](https://github.com/TuxWeb/pyroshape)
- [TuxWeb](https://www.tuxweb.it/)
