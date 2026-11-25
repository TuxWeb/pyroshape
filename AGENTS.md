# Pyroshape Core

Pyroshape is TuxWeb's open-source PHP framework. The repository root is the `tuxweb-it/pyroshape` Composer package for PHP 8.5 or later. Core provides Slim/PHP-DI bootstrapping, middleware, authentication, logging, JSON error handling, and PDO access. `apps/skeleton/` is the starter for new apps and `apps/demo/` shows how to integrate Core. Keep application routes, domain logic, frontends, and app-specific migrations in the apps. Both bundled apps resolve Core from the root of this repository.

## Working approach

- Explicit user instructions take precedence over the preferences in this guide.
- Review the relevant code, contracts, and documentation before making changes. **Identify the root cause before applying a fix** and use the evidence to explain the change.
- Favor simple, modular, maintainable solutions. Respect existing boundaries and avoid premature abstractions, unnecessary dependencies, and unrelated refactoring.
- Preserve the contracts that apps rely on, including public signatures, configuration keys, route and middleware order, and session behavior. If a requested change affects these contracts, document the impact and verify it in at least one representative app.
- Break complex implementation goals into verifiable milestones and complete one increment at a time. State material risks and assumptions and make routine decisions independently.
- Report implementation, verification, repository publishing, and deployment as separate statuses.

## Subagents and delegation

- Use subagents for **substantial, independent tasks that can run in parallel**. Use **`gpt-6.2-sol`** for development, architecture, and review, with a reasoning level suited to the assignment.
  - `low` for inventories, initial investigation, and mechanical edits.
  - `medium` for routine implementation.
  - `high` for architecture, review, and significant design decisions.
  - `xhigh` for especially complex problems, disagreements, or risks involving finance, concurrency, or data integrity.
- Use **at most three workers concurrently**, including any test worker. Give each worker a goal, acceptance criteria, and distinct file ownership. Handle small, sequential, closely coupled, or overlapping changes directly.
- Use `gpt-6-luna` with `high` reasoning for **independent test work**. The worker may edit only test files and must keep expectations aligned with the requirements when the implementation diverges. If the specified model is unavailable, handle the assignment directly or report the limitation.
- Request an **independent review** when the risk warrants it, particularly for security, finance, concurrency, migrations, and data integrity.
- Each worker reports changes, checks, evidence, open issues, and limitations. The coordinator integrates and verifies the results.

## Databases and data management

- Keep application logic in PHP and out of triggers, stored procedures, and stored functions. Use declarative constraints, indexes, and foreign keys to protect data integrity.
- Design normalized schemas with foreign keys between related tables. Use `UNIQUE`, `NOT NULL`, and `CHECK` constraints where appropriate, and base indexes on actual access patterns.
- Use dedicated schemas and database users with the least privileges needed for each module, unless the user directs otherwise. Define data ownership and provide retention policies and verifiable batch cleanup for data that accumulates over time.
- Track migrations in version control and verify the affected migrations on the target database engine. `database/migrations/001_auth_schema.sql` is the MySQL baseline. Apps manage their own migrations. For PDO changes, test the affected engine and preserve existing compatibility that apps rely on.
- Work in a development environment by default and use test data for resets. Before destructive operations, verify the target, impact, and recovery options. Protect real, unique, or important data.

## Simplicity, performance, and reliability

- Avoid unnecessary queries, N+1 patterns, and repeated round trips. Batch reads and writes where beneficial and keep transactions and locks short.
- Measure bottlenecks before introducing caches, queues, or architectural complexity. Favor simple, predictable workflows.
- Use configurable budgets, timeouts, and limits with enough headroom for normal load and reasonable peaks. Bound resource and queue usage and handle saturation explicitly.
- Keep credentials, tokens, and keys out of repositories, logs, fixtures, and API responses.

## Testing and verification

- Test relevant behavior and risks. Prefer **red–green testing** for regressions and changes involving finance, concurrency, security, or data integrity. Confirm that the test reproduces the problem before the fix and passes afterward.
- Use **at most three focused tests per task** by default. Add cases when the risk or distinct behaviors justify them. Skip tests for static markup, CSS, and trivial accessors.
- Run the smallest useful checks during implementation. Run the relevant suite once at the end. Use `php -l` to check the syntax of modified PHP files.
- Core tests are standalone PHP scripts in `tests/`. Run each in a separate process with `php tests/<script>.php`. Follow the `pyroshape-app` skill for authentication tests and their SQLite and PostgreSQL requirements.
- Use isolated fixtures and test services. Report which checks passed, which were skipped, and why.

## Milestones, Git, and deployment

- At each implementation milestone, verify the result, commit only the relevant changes with a descriptive message, and push to the working branch unless the user directs otherwise. Report any blocked step and its actual status.
- Deploy at the end of the goal or when a milestone explicitly warrants it, unless directed otherwise. Verify the target, essential dependencies, and potential effects on data before deploying.
- For goals with multiple milestones, give brief periodic updates with estimated progress percentages for the current milestone and the overall goal, along with the work that remains.
- In the final report, summarize the changes, verification results, commit and push status, deployment status, and relevant limitations.

## Documentation

- Write and maintain all project documentation in English, including existing documents and new additions.
- Keep `AGENTS.md` short and stable. Record requirements and architectural decisions in `docs/specs/`, procedures in `docs/operations/`, and relevant findings in `docs/reports/`, or use the equivalent locations in the existing documentation.
- Update the authoritative document when contracts change. Use `README.md` for package installation, development, and releases. Use the [pyroshape-app skill](skills/pyroshape-app/SKILL.md) for workflows shared by Core, the skeleton, and consuming apps.
