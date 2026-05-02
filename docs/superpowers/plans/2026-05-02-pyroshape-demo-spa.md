# Pyroshape Demo SPA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `pyroshape-demo` as a full Pyroshape-consuming SPA demo with auth, protected navigation, and Projects + Tasks CRUD.

**Architecture:** Add a small extension hook to `pyroshape-core` so consuming apps can register routes before the SPA fallback. Bootstrap `pyroshape-demo` from `pyroshape-skeleton`, then add app-owned PHP controllers/repositories/migrations/tests and a stronger SvelteKit static SPA. Keep reusable backend behavior in core and app-specific domain/frontend behavior in demo.

**Tech Stack:** PHP 8.5, Slim, PHP-DI, PDO, Monolog, Firebase JWT, SvelteKit static SPA, Svelte 5, TypeScript, Vite, Tailwind CSS, Composer path repository.

---

## File Structure

### `pyroshape-core`

- Modify `src/App.php`: split route registration so subclasses can add app-specific routes before SPA fallback.
- Create `tests/app_routes_before_spa_fallback.php`: verifies app routes registered by a subclass are reachable and not swallowed by fallback.

### `pyroshape-demo`

- Copy baseline app files from `pyroshape-skeleton`: `composer.json`, `config/config.php`, `public/index.php`, `src/App.php`, `src/Exceptions/ConfigNotValidException.php`, `tests/*`, `frontend/*`.
- Modify namespace/package names from `Dam2k\PyroshapeSkeleton` to `Dam2k\PyroshapeDemo`.
- Create `database/migrations/001_auth_schema.sql`: copied from core base auth schema.
- Create `database/migrations/002_projects_tasks_schema.sql`: app domain tables.
- Create `src/Controllers/ProjectController.php`: HTTP JSON handlers for project/task endpoints.
- Create `src/Domain/Projects/ProjectRepository.php`: SQL persistence and user scoping.
- Modify `src/App.php`: register app routes through the new core hook.
- Create `tests/project_api.php`: end-to-end PHP API test for auth, ownership, CRUD, validation, and JSON errors.
- Modify `frontend/src/lib/api.ts`: keep typed JSON API helper and add reusable mutation helpers only if needed.
- Modify `frontend/src/lib/session.ts`: expose login/logout/register/session helpers.
- Create `frontend/src/lib/domain/projects.ts`: typed project/task API client.
- Create UI/layout components under `frontend/src/lib/components/ui/*` and `frontend/src/lib/components/layout/*`.
- Modify routes under `frontend/src/routes/*`: app shell, dashboard, project list, project detail, auth pages.
- Create `tests/demo_spa_smoke.php` or `scripts/demo_spa_smoke.php`: post-build smoke path against PHP server if practical in this environment.

---

### Task 1: Add Core Route Extension Hook

**Files:**
- Modify: `/home/dino/projects/pyroshape-core/src/App.php`
- Create: `/home/dino/projects/pyroshape-core/tests/app_routes_before_spa_fallback.php`

- [ ] **Step 1: Write the failing route-extension test**

Create `tests/app_routes_before_spa_fallback.php` with this content:

```php
<?php

declare(strict_types=1);

use Dam2k\Pyroshape\Core\App;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';

final class RouteHookTestApp extends App
{
    protected function setupAppRoutes(): void
    {
        $this->getSlimApp()->get('/api/demo/ping', function (Request $request, Response $response): Response {
            $response->getBody()->write(json_encode(['pong' => true], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });
    }
}

$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$testId = bin2hex(random_bytes(6));
$publicApp = "$tmpDir/route-hook-$testId-app";
mkdir($publicApp, 0775, true);
file_put_contents($publicApp . '/index.html', '<!doctype html><script src="/_app/immutable/app.js"></script>');

$configFile = "$tmpDir/route-hook-$testId-config.php";
$config = [
    'settings' => [
        'environment' => 'development',
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'RouteHookTest',
            'path' => "$tmpDir/route-hook-$testId-app.log",
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% [%channel%.%level_name% %extra%] %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'RouteHookTest',
            'path' => "$tmpDir/route-hook-$testId-access.log",
            'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => ['X-Pyroshape' => '1'],
        'frontend' => ['appPath' => $publicApp],
        'database' => ['dsn' => 'sqlite::memory:'],
        'jwt_refresh_token' => ['secret' => hash('sha256', 'refresh'), 'ttl' => 3600, 'algorithm' => 'HS256'],
        'jwt_access_token' => ['secret' => hash('sha256', 'access'), 'ttl' => 600, 'algorithm' => 'HS256'],
        'login_cookie' => [
            'name' => 'route_hook_session',
            'ttl' => 3600,
            'path' => '/',
            'secure' => false,
            'httpOnly' => true,
            'sameSite' => 'Lax',
            'key' => hash('sha256', 'cookie-key'),
            'salt' => 'cookie-salt',
        ],
        'emailVerification' => ['devExposeToken' => true],
    ],
];
file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

register_shutdown_function(static function () use ($tmpDir, $testId, $publicApp, $configFile): void {
    @unlink($configFile);
    @unlink("$tmpDir/route-hook-$testId-app.log");
    @unlink("$tmpDir/route-hook-$testId-access.log");
    @unlink($publicApp . '/index.html');
    @rmdir($publicApp);
});

$app = (new RouteHookTestApp($configFile))->getSlimApp();
$response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/demo/ping'));
$body = (string) $response->getBody();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected /api/demo/ping status 200, got {$response->getStatusCode()}\n$body\n");
    exit(1);
}

if (!str_contains($response->getHeaderLine('Content-Type'), 'application/json')) {
    fwrite(STDERR, "Expected JSON content type, got {$response->getHeaderLine('Content-Type')}\n");
    exit(1);
}

$payload = json_decode($body, true);
if (($payload['pong'] ?? null) !== true) {
    fwrite(STDERR, "Expected pong payload, got $body\n");
    exit(1);
}

echo "app routes before spa fallback ok\n";
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
php tests/app_routes_before_spa_fallback.php
```

Expected: fatal error similar to `Access level to RouteHookTestApp::setupAppRoutes() must be public` or route returns 404 because `setupAppRoutes()` does not exist or is not called.

- [ ] **Step 3: Add the route hook before SPA fallback**

Modify `src/App.php` so `setupRoutes()` calls a protected hook before fallback routes:

```php
private function setupRoutes(): void
{
    $this->slimApp->get('/health', [HealthController::class, 'getHealth'])->setName('health');
    $this->slimApp->post('/api/auth/register', [AuthController::class, 'postRegister'])->setName('auth.register');
    $this->slimApp->get('/api/auth/verify-email', [AuthController::class, 'getVerifyEmail'])
        ->setName('auth.verify-email');
    $this->slimApp->post('/api/auth/login', [AuthController::class, 'postLogin'])->setName('auth.login');
    $this->slimApp->post('/api/auth/logout', [AuthController::class, 'postLogout'])->setName('auth.logout');
    $this->slimApp->get('/api/session', [AuthController::class, 'getSession'])->setName('session');

    $this->setupAppRoutes();
    $this->setupSpaRoutes();
}

protected function setupAppRoutes(): void
{
}

private function setupSpaRoutes(): void
{
    $this->slimApp->get('/_app/{asset:.*}', function (Request $request, Response $response, string $asset): Response {
        return $this->serveSpaAsset($request, $response, '_app/' . $asset);
    })->setName('spa.root-asset');
    $this->slimApp->get('/app/{asset:.*}', function (Request $request, Response $response, string $asset): Response {
        return $this->serveSpaAsset($request, $response, $asset);
    })->setName('spa.asset');

    $spaFallback = fn (Request $request, Response $response): Response => $this->serveSpaFallback(
        $request,
        $response
    );
    $this->slimApp->get('/', $spaFallback)->setName('spa.root');
    $this->slimApp->get('/{path:.*}', $spaFallback)->setName('spa.fallback');
}
```

Keep the existing `serveSpaFallback()`, `serveSpaAsset()`, `contentTypeForFile()`, and `frontendAppPath()` methods unchanged.

- [ ] **Step 4: Run focused and existing core tests**

Run:

```bash
php tests/app_routes_before_spa_fallback.php
```

Expected: `app routes before spa fallback ok`.

Also run the existing skeleton SPA fallback test after the demo exists, or from `pyroshape-skeleton` now:

```bash
php tests/spa_fallback.php
```

Expected: `spa fallback ok`.

- [ ] **Step 5: Commit core hook**

```bash
git add src/App.php tests/app_routes_before_spa_fallback.php
git commit -m "Add app route hook before SPA fallback"
```

---

### Task 2: Bootstrap `pyroshape-demo` From Skeleton

**Files:**
- Create/modify under `/home/dino/projects/pyroshape-demo`: app, config, public, tests, frontend baseline.

- [ ] **Step 1: Copy skeleton files into demo**

Run from `/home/dino/projects/pyroshape-demo`:

```bash
rsync -a --exclude='.git' --exclude='public/app' --exclude='logs' --exclude='tmp' /home/dino/projects/pyroshape-skeleton/ /home/dino/projects/pyroshape-demo/
```

Expected: `composer.json`, `public/index.php`, `config/config.php`, `src/App.php`, `frontend/package.json`, and skeleton tests exist in `pyroshape-demo`.

- [ ] **Step 2: Rename package and namespace**

Modify these exact values:

```json
{
  "name": "dam2k/pyroshape-demo",
  "description": "Pyroshape demo application",
  "autoload": {
    "psr-4": {
      "Dam2k\\PyroshapeDemo\\": "src/"
    }
  }
}
```

In PHP files, replace:

```php
Dam2k\PyroshapeSkeleton
```

with:

```php
Dam2k\PyroshapeDemo
```

In `public/index.php`, import:

```php
use Dam2k\PyroshapeDemo\App;
```

- [ ] **Step 3: Install Composer dependencies**

Run:

```bash
php /home/dino/temp/prove/php/composer.phar install
```

Expected: Composer installs `dam2k/pyroshape-core` from `../pyroshape-core` path repository and generates autoload files.

- [ ] **Step 4: Install frontend dependencies if `node_modules` is absent**

Run from `frontend`:

```bash
npm install
```

Expected: package install completes and `package-lock.json` matches dependencies.

- [ ] **Step 5: Run baseline verification**

Run from `/home/dino/projects/pyroshape-demo`:

```bash
php /home/dino/temp/prove/php/composer.phar validate
cd frontend && npm run check && npm run build
cd .. && php tests/spa_fallback.php && php tests/smoke.php
```

Expected: Composer validates, Svelte check/build pass, SPA fallback and smoke tests pass.

- [ ] **Step 6: Commit bootstrap**

```bash
git add composer.json composer.lock config public src tests frontend database README.md
git commit -m "Bootstrap Pyroshape demo from skeleton"
```

---

### Task 3: Add Projects + Tasks Backend

**Files:**
- Create: `database/migrations/002_projects_tasks_schema.sql`
- Create: `src/Domain/Projects/ProjectRepository.php`
- Create: `src/Controllers/ProjectController.php`
- Modify: `src/App.php`
- Create: `tests/project_api.php`

- [ ] **Step 1: Write failing project API test**

Create `tests/project_api.php` that follows the helper style from `tests/auth_flow.php`. It must create a temporary SQLite DB with auth tables plus `projects` and `tasks`, register and verify two users, log in as both, and assert:

```php
// unauthenticated requests are rejected
GET /api/projects => 401

// user A can create and list a project
POST /api/projects {"name":"Demo project","description":"First Pyroshape project"} => 201
GET /api/projects => includes Demo project

// validation rejects blank names
POST /api/projects {"name":""} => 400 with error.message

// user A can add and complete a task
POST /api/projects/{id}/tasks {"title":"Build frontend shell"} => 201
PATCH /api/tasks/{id} {"completed":true} => 200

// user B cannot see or mutate user A records
GET /api/projects/{userAProjectId} => 404
PATCH /api/tasks/{userATaskId} {"completed":false} => 404

// user A can delete task and project
DELETE /api/tasks/{id} => 200
DELETE /api/projects/{id} => 200
```

Use existing `jsonRequest()`, `responseJson()`, `extractCookie()`, and assertion helpers by copying them from `tests/auth_flow.php` into this standalone test.

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php tests/project_api.php
```

Expected: failure at first authenticated domain route because `/api/projects` is not implemented.

- [ ] **Step 3: Add domain schema**

Create `database/migrations/002_projects_tasks_schema.sql`:

```sql
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    users_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX idx_projects_users_id ON projects (users_id);

CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    projects_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    completed INTEGER NOT NULL DEFAULT 0,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX idx_tasks_projects_id ON tasks (projects_id);
```

For MySQL deployments, keep the same logical columns and convert `INTEGER PRIMARY KEY AUTOINCREMENT` to the project’s MySQL auto-increment style when adding a MySQL migration later.

- [ ] **Step 4: Implement repository**

Create `src/Domain/Projects/ProjectRepository.php` with methods:

```php
public function listProjects(int $userId): array;
public function createProject(int $userId, string $name, ?string $description): array;
public function getProjectWithTasks(int $userId, int $projectId): ?array;
public function updateProject(int $userId, int $projectId, string $name, ?string $description): ?array;
public function deleteProject(int $userId, int $projectId): bool;
public function createTask(int $userId, int $projectId, string $title): ?array;
public function updateTask(int $userId, int $taskId, ?string $title, ?bool $completed, ?int $position): ?array;
public function deleteTask(int $userId, int $taskId): bool;
```

Use prepared PDO statements for every query. Scope every read/write through `projects.users_id = :user_id`. Return arrays with camelCase keys for frontend payloads:

```php
[
    'id' => 1,
    'name' => 'Demo project',
    'description' => 'First Pyroshape project',
    'createdAt' => '2026-05-02 12:00:00',
    'updatedAt' => '2026-05-02 12:00:00',
]
```

Task arrays must use:

```php
[
    'id' => 1,
    'projectId' => 1,
    'title' => 'Build frontend shell',
    'completed' => false,
    'position' => 0,
    'createdAt' => '2026-05-02 12:00:00',
    'updatedAt' => '2026-05-02 12:00:00',
]
```

- [ ] **Step 5: Implement controller**

Create `src/Controllers/ProjectController.php` with methods matching the routes:

```php
public function listProjects(Request $request, Response $response): Response;
public function createProject(Request $request, Response $response): Response;
public function getProject(Request $request, Response $response, string $id): Response;
public function updateProject(Request $request, Response $response, string $id): Response;
public function deleteProject(Request $request, Response $response, string $id): Response;
public function createTask(Request $request, Response $response, string $projectId): Response;
public function updateTask(Request $request, Response $response, string $id): Response;
public function deleteTask(Request $request, Response $response, string $id): Response;
```

Read the authenticated user id from:

```php
$request->getAttribute(\Dam2k\Pyroshape\Core\Middlewares\Auth\CheckValidAuthToken::UID_ATTRIBUTE)
```

Validation rules:

```php
trim($name) !== ''
mb_strlen($name) <= 120
trim($title) !== ''
mb_strlen($title) <= 180
```

JSON response shapes:

```php
['projects' => $projects]
['project' => $project]
['task' => $task]
['deleted' => true]
['error' => ['message' => 'Project name is required']]
```

Use `400` for validation, `404` for missing or unauthorized records, `201` for creates, and `200` for successful reads/updates/deletes.

- [ ] **Step 6: Register authenticated routes**

Modify `src/App.php`:

```php
<?php

declare(strict_types=1);

namespace Dam2k\PyroshapeDemo;

use Dam2k\Pyroshape\Core\Middlewares\Auth\CheckValidAuthToken;
use Dam2k\Pyroshape\Core\Middlewares\Auth\RequireAuth;
use Dam2k\PyroshapeDemo\Controllers\ProjectController;
use Dam2k\PyroshapeDemo\Exceptions\ConfigNotValidException;

class App extends \Dam2k\Pyroshape\Core\App
{
    public function __construct(string $config)
    {
        try {
            parent::__construct($config);
        } catch (\Dam2k\Pyroshape\Core\Exceptions\ConfigNotValidException $exception) {
            throw new ConfigNotValidException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    protected function setupAppRoutes(): void
    {
        $app = $this->getSlimApp();

        $app->group('/api', function ($group): void {
            $group->get('/projects', [ProjectController::class, 'listProjects']);
            $group->post('/projects', [ProjectController::class, 'createProject']);
            $group->get('/projects/{id}', [ProjectController::class, 'getProject']);
            $group->patch('/projects/{id}', [ProjectController::class, 'updateProject']);
            $group->delete('/projects/{id}', [ProjectController::class, 'deleteProject']);
            $group->post('/projects/{projectId}/tasks', [ProjectController::class, 'createTask']);
            $group->patch('/tasks/{id}', [ProjectController::class, 'updateTask']);
            $group->delete('/tasks/{id}', [ProjectController::class, 'deleteTask']);
        })->add(RequireAuth::class)->add(CheckValidAuthToken::class);
    }
}
```

- [ ] **Step 7: Run backend domain test**

Run:

```bash
php tests/project_api.php
```

Expected: test passes and prints `project api ok`.

- [ ] **Step 8: Commit backend domain**

```bash
git add database src tests/project_api.php
git commit -m "Add authenticated projects and tasks API"
```

---

### Task 4: Strengthen Frontend App Shell and Auth Flow

**Files:**
- Modify: `frontend/src/lib/api.ts`
- Modify: `frontend/src/lib/session.ts`
- Create: `frontend/src/lib/components/ui/EmptyState.svelte`
- Create: `frontend/src/lib/components/ui/ErrorMessage.svelte`
- Create: `frontend/src/lib/components/ui/LoadingState.svelte`
- Create: `frontend/src/lib/components/ui/FormField.svelte`
- Create: `frontend/src/lib/components/layout/AppShell.svelte`
- Modify: `frontend/src/routes/+layout.svelte`
- Modify: `frontend/src/routes/+page.svelte`
- Modify: auth pages as needed

- [ ] **Step 1: Run current frontend checks**

Run from `frontend`:

```bash
npm run check
```

Expected: current check passes before edits.

- [ ] **Step 2: Add UI state components**

Create components with these public props:

```svelte
<!-- EmptyState.svelte -->
<script lang="ts">
  export let title: string;
  export let description: string;
</script>

<div class="grid gap-2 rounded-md border border-dashed border-border bg-white px-5 py-8 text-center">
  <h2 class="text-base font-semibold">{title}</h2>
  <p class="text-sm text-muted-foreground">{description}</p>
  <slot />
</div>
```

```svelte
<!-- ErrorMessage.svelte -->
<script lang="ts">
  export let message: string;
</script>

{#if message}
  <p class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
    {message}
  </p>
{/if}
```

```svelte
<!-- LoadingState.svelte -->
<script lang="ts">
  export let label = 'Loading...';
</script>

<div class="rounded-md border border-border bg-white px-5 py-4 text-sm text-muted-foreground">
  {label}
</div>
```

```svelte
<!-- FormField.svelte -->
<script lang="ts">
  export let label: string;
  export let error = '';
</script>

<label class="grid gap-2 text-sm font-medium">
  <span>{label}</span>
  <slot />
  {#if error}
    <span class="text-xs font-normal text-destructive">{error}</span>
  {/if}
</label>
```

- [ ] **Step 3: Add app shell**

Create `frontend/src/lib/components/layout/AppShell.svelte` with top navigation for Dashboard, Projects, Login/Register or Logout. It must accept session state through imports from `$lib/session` and expose a `<slot />` for route content.

Use restrained classes:

```svelte
<div class="min-h-screen bg-background text-foreground">
  <header class="border-b border-border bg-white">
    <div class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between px-4">
      <a href="/dashboard" class="text-base font-semibold">Pyroshape Demo</a>
      <nav class="flex items-center gap-1 text-sm text-muted-foreground">
        <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/dashboard">Dashboard</a>
        <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/projects">Projects</a>
      </nav>
    </div>
  </header>
  <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:py-8">
    <slot />
  </main>
</div>
```

- [ ] **Step 4: Wire layout and root route**

Modify `+layout.svelte` to load session on mount and render `AppShell`. Modify `/` route to redirect based on session, preserving the existing `goto(current.authenticated ? '/dashboard' : '/login')` behavior.

- [ ] **Step 5: Run frontend check**

Run:

```bash
npm run check
```

Expected: check passes.

- [ ] **Step 6: Commit app shell**

```bash
git add frontend/src
git commit -m "Strengthen SPA shell and auth states"
```

---

### Task 5: Add Frontend Projects + Tasks UI

**Files:**
- Create: `frontend/src/lib/domain/projects.ts`
- Modify: `frontend/src/routes/dashboard/+page.svelte`
- Create/modify: `frontend/src/routes/projects/+page.svelte`
- Create: `frontend/src/routes/projects/[id]/+page.svelte`

- [ ] **Step 1: Create typed project API client**

Create `frontend/src/lib/domain/projects.ts`:

```ts
import { apiJson } from '$lib/api';

export type Task = {
  id: number;
  projectId: number;
  title: string;
  completed: boolean;
  position: number;
  createdAt: string;
  updatedAt: string;
};

export type Project = {
  id: number;
  name: string;
  description: string | null;
  createdAt: string;
  updatedAt: string;
};

export type ProjectDetail = Project & {
  tasks: Task[];
};

export async function listProjects(): Promise<Project[]> {
  const response = await apiJson<{ projects: Project[] }>('/api/projects');
  return response.projects;
}

export async function createProject(input: { name: string; description?: string | null }): Promise<Project> {
  const response = await apiJson<{ project: Project }>('/api/projects', {
    method: 'POST',
    body: JSON.stringify(input)
  });
  return response.project;
}

export async function getProject(id: number): Promise<ProjectDetail> {
  const response = await apiJson<{ project: ProjectDetail }>(`/api/projects/${id}`);
  return response.project;
}

export async function updateProject(id: number, input: { name: string; description?: string | null }): Promise<ProjectDetail> {
  const response = await apiJson<{ project: ProjectDetail }>(`/api/projects/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input)
  });
  return response.project;
}

export async function deleteProject(id: number): Promise<void> {
  await apiJson<{ deleted: true }>(`/api/projects/${id}`, { method: 'DELETE' });
}

export async function createTask(projectId: number, input: { title: string }): Promise<Task> {
  const response = await apiJson<{ task: Task }>(`/api/projects/${projectId}/tasks`, {
    method: 'POST',
    body: JSON.stringify(input)
  });
  return response.task;
}

export async function updateTask(id: number, input: { title?: string; completed?: boolean; position?: number }): Promise<Task> {
  const response = await apiJson<{ task: Task }>(`/api/tasks/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input)
  });
  return response.task;
}

export async function deleteTask(id: number): Promise<void> {
  await apiJson<{ deleted: true }>(`/api/tasks/${id}`, { method: 'DELETE' });
}
```

- [ ] **Step 2: Implement dashboard summary**

Modify `dashboard/+page.svelte` to load `listProjects()` on mount after session is authenticated. Show:

```text
Total projects
Open tasks
Completed tasks
Recent projects
```

If unauthenticated, show an unauthenticated state with link to `/login`.

- [ ] **Step 3: Implement project list route**

Create `routes/projects/+page.svelte` with:

- protected session state
- project creation form with name and description
- project cards/table with links to `/projects/{id}`
- delete action with confirm
- `EmptyState` when there are no projects
- `LoadingState` while loading
- `ErrorMessage` for API errors

- [ ] **Step 4: Implement project detail route**

Create `routes/projects/[id]/+page.svelte` with:

- load project id from `$page.params.id`
- project metadata edit form
- task creation form
- task list with complete checkbox, title edit, and delete
- reload-after-save after each mutation
- not-found/API error state

- [ ] **Step 5: Run frontend checks and build**

Run:

```bash
npm run check
npm run build
```

Expected: Svelte check passes and static build writes to `../public/app`.

- [ ] **Step 6: Commit frontend domain UI**

```bash
git add frontend/src public/app
git commit -m "Add projects and tasks SPA UI"
```

Do not commit `public/app` if `.gitignore` excludes generated build output; in that case commit only source files.

---

### Task 6: End-to-End Verification and Documentation

**Files:**
- Modify: `README.md`
- Create: `tests/demo_runtime_smoke.php` if practical

- [ ] **Step 1: Update README**

Document:

```md
# Pyroshape Demo

This app demonstrates a Pyroshape-driven single page application using `dam2k/pyroshape-core`.

## Run

php /home/dino/temp/prove/php/composer.phar install
cd frontend
npm install
npm run build
cd ..
php -S 127.0.0.1:8080 -t public

Open http://127.0.0.1:8080

## Verify

php tests/auth_flow.php
php tests/project_api.php
php tests/spa_fallback.php
cd frontend && npm run check && npm run build
```

- [ ] **Step 2: Run full verification**

Run from `/home/dino/projects/pyroshape-core`:

```bash
php tests/app_routes_before_spa_fallback.php
```

Run from `/home/dino/projects/pyroshape-demo`:

```bash
php /home/dino/temp/prove/php/composer.phar validate
php tests/core_package.php
php tests/auth_flow.php
php tests/auth_service.php
php tests/token_cookie.php
php tests/project_api.php
cd frontend && npm run check && npm run build
cd .. && php tests/spa_fallback.php && php tests/smoke.php && php tests/access_logger.php
```

Expected: every command passes. If any command fails because local MySQL is not configured, record the exact failure and rerun the equivalent SQLite-backed tests that cover the changed code.

- [ ] **Step 3: Start local server for manual review**

Run:

```bash
php -S 127.0.0.1:8080 -t public
```

Expected: server starts. Open `http://127.0.0.1:8080`, register, verify email with dev link, login, create project, create task, complete task, delete task, delete project, log out.

- [ ] **Step 4: Commit docs and verification support**

```bash
git add README.md tests/demo_runtime_smoke.php
git commit -m "Document Pyroshape demo SPA workflow"
```

Skip `tests/demo_runtime_smoke.php` in the commit command if no runtime smoke file was added.

---

## Self-Review

Spec coverage:

- Core/app boundary: Task 1 and Task 2.
- Static SvelteKit SPA served by PHP fallback: Task 2, Task 6.
- Authenticated Projects + Tasks API: Task 3.
- Protected frontend routes and app shell: Task 4 and Task 5.
- Loading, empty, validation, API error, unauthenticated states: Task 4 and Task 5.
- Backend and frontend verification: Task 1, Task 2, Task 3, Task 5, Task 6.

No placeholders are intentionally left in this plan. If implementation uncovers a concrete incompatibility, update this plan with the exact new file/step before continuing.
