<?php

declare(strict_types=1);

use Dam2k\PyroshapeDemo\App;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param array<string, mixed>|null $payload
 */
function jsonRequest(string $method, string $uri, ?array $payload = null, ?string $cookie = null): ServerRequestInterface
{
    $request = (new ServerRequestFactory())->createServerRequest($method, $uri)
        ->withHeader('Accept', 'application/json');

    if ($payload !== null) {
        $body = (new StreamFactory())->createStream(json_encode($payload, JSON_THROW_ON_ERROR));
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    if ($cookie !== null && $cookie !== '') {
        $request = $request
            ->withHeader('Cookie', $cookie)
            ->withCookieParams(parseCookieHeader($cookie));
    }

    return $request;
}

/**
 * @return array<string, string>
 */
function parseCookieHeader(string $cookie): array
{
    $cookies = [];

    foreach (explode(';', $cookie) as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $part, 2);
        $cookies[$name] = urldecode($value);
    }

    return $cookies;
}

/**
 * @return array<string, mixed>
 */
function responseJson(ResponseInterface $response): array
{
    $body = (string) $response->getBody();
    $payload = json_decode($body, true);

    if (!is_array($payload)) {
        fwrite(STDERR, "Expected JSON object response, got: $body\n");
        exit(1);
    }

    return $payload;
}

function assertStatus(ResponseInterface $response, int $expected, string $label): void
{
    $actual = $response->getStatusCode();
    if ($actual !== $expected) {
        fwrite(STDERR, "$label: expected status $expected, got $actual\n");
        fwrite(STDERR, "Response body: " . (string) $response->getBody() . "\n");
        exit(1);
    }
}

/**
 * @param array<string, mixed> $payload
 */
function assertPayloadValue(array $payload, string $key, mixed $expected, string $label): void
{
    if (($payload[$key] ?? null) !== $expected) {
        fwrite(STDERR, "$label: expected JSON key \"$key\" to be " . var_export($expected, true) . "\n");
        fwrite(STDERR, 'Payload: ' . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
}

/**
 * @param array<string, mixed> $payload
 */
function assertNestedMessage(array $payload, string $label): void
{
    if (!isset($payload['error']) || !is_array($payload['error']) || !is_string($payload['error']['message'] ?? null)) {
        fwrite(STDERR, "$label: expected JSON error message\n");
        fwrite(STDERR, 'Payload: ' . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
}

function extractCookie(ResponseInterface $response, string $cookieName, string $label): string
{
    foreach ($response->getHeader('Set-Cookie') as $header) {
        $cookiePair = explode(';', $header, 2)[0];
        if (str_starts_with($cookiePair, $cookieName . '=')) {
            return $cookiePair;
        }
    }

    fwrite(STDERR, "$label: expected Set-Cookie header for \"$cookieName\"\n");
    fwrite(STDERR, 'Set-Cookie headers: ' . json_encode($response->getHeader('Set-Cookie')) . "\n");
    exit(1);
}

/**
 * @return array{email: string, cookie: string}
 */
function registerVerifiedUser(\Slim\App $slim, string $email, string $password, string $loginCookieName): array
{
    $registerResponse = $slim->handle(jsonRequest('POST', '/api/auth/register', [
        'email' => $email,
        'password' => $password,
        'nickname' => $email,
    ]));
    assertStatus($registerResponse, 201, "register $email");
    $registerPayload = responseJson($registerResponse);
    $verificationToken = $registerPayload['verificationToken'] ?? null;
    if (!is_string($verificationToken) || $verificationToken === '') {
        fwrite(STDERR, "register $email: expected verification token\n");
        exit(1);
    }

    $verifyResponse = $slim->handle(jsonRequest('GET', '/api/auth/verify-email?token=' . rawurlencode($verificationToken)));
    assertStatus($verifyResponse, 200, "verify $email");

    $loginResponse = $slim->handle(jsonRequest('POST', '/api/auth/login', [
        'email' => $email,
        'password' => $password,
    ]));
    assertStatus($loginResponse, 200, "login $email");

    return [
        'email' => $email,
        'cookie' => extractCookie($loginResponse, $loginCookieName, "login $email"),
    ];
}

$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$testId = bin2hex(random_bytes(6));
$appLog = "$tmpDir/test-project-api-$testId-app.log";
$accessLog = "$tmpDir/test-project-api-$testId-access.log";
$sqliteFile = "$tmpDir/test-project-api-$testId.sqlite";
$configFile = "$tmpDir/test-project-api-$testId-config.php";
$loginCookieName = 'pyroshape_project_session';

$config = [
    'settings' => [
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapeProjectApiTest',
            'path' => $appLog,
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% [%channel%.%level_name% %extra%] %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'PyroshapeProjectApiTest',
            'path' => $accessLog,
            'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => [
            'X-Pyroshape' => '1',
            'X-Robots-Tag' => 'noindex, nofollow',
        ],
        'database' => [
            'driver' => 'sqlite',
            'dsn' => 'sqlite:' . $sqliteFile,
            'path' => $sqliteFile,
        ],
        'jwt_refresh_token' => [
            'secret' => hash('sha256', 'project-api-refresh-secret'),
            'ttl' => 60 * 60 * 24 * 30,
            'algorithm' => 'HS256',
        ],
        'jwt_access_token' => [
            'secret' => hash('sha256', 'project-api-access-secret'),
            'ttl' => 60 * 15,
            'algorithm' => 'HS256',
        ],
        'login_cookie' => [
            'name' => $loginCookieName,
            'ttl' => 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => false,
            'httpOnly' => true,
            'sameSite' => 'Lax',
            'key' => hash('sha256', 'project-api-cookie-key'),
            'salt' => 'project-api-cookie-salt',
        ],
        'emailVerification' => [
            'devExposeToken' => true,
        ],
    ],
];

file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

$pdo = new PDO('sqlite:' . $sqliteFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    pwdhash TEXT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    email_verified INTEGER NOT NULL DEFAULT 0,
    nickname TEXT NULL,
    name TEXT NULL,
    surname TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE auth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    users_id INTEGER NOT NULL,
    jti TEXT NOT NULL UNIQUE,
    token_hash TEXT NOT NULL,
    released TEXT NOT NULL,
    expiration TEXT NOT NULL,
    revocation_time TEXT NULL
);
CREATE TABLE email_verification_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    users_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    expiration TEXT NOT NULL,
    used_at TEXT NULL
);
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    users_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    projects_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    completed INTEGER NOT NULL DEFAULT 0,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (projects_id) REFERENCES projects (id) ON DELETE CASCADE
);
SQL);

register_shutdown_function(static function () use ($appLog, $accessLog, $sqliteFile, $configFile): void {
    @unlink($appLog);
    @unlink($accessLog);
    @unlink($sqliteFile);
    @unlink($configFile);
});

$slim = (new App($configFile))->getSlimApp();
$password = 'CorrectHorseBatteryStaple!42';
$userA = registerVerifiedUser($slim, "project-api-a-$testId@example.test", $password, $loginCookieName);
$userB = registerVerifiedUser($slim, "project-api-b-$testId@example.test", $password, $loginCookieName);

$unauthenticatedResponse = $slim->handle(jsonRequest('GET', '/api/projects'));
assertStatus($unauthenticatedResponse, 401, 'unauthenticated project list');
assertNestedMessage(responseJson($unauthenticatedResponse), 'unauthenticated project list');

$emptyListResponse = $slim->handle(jsonRequest('GET', '/api/projects', null, $userA['cookie']));
assertStatus($emptyListResponse, 200, 'empty project list');
$emptyListPayload = responseJson($emptyListResponse);
if (($emptyListPayload['projects'] ?? null) !== []) {
    fwrite(STDERR, "empty project list: expected empty projects array\n");
    exit(1);
}

$invalidProjectResponse = $slim->handle(jsonRequest('POST', '/api/projects', ['name' => '   '], $userA['cookie']));
assertStatus($invalidProjectResponse, 400, 'blank project name');
assertNestedMessage(responseJson($invalidProjectResponse), 'blank project name');

$longProjectResponse = $slim->handle(jsonRequest('POST', '/api/projects', ['name' => str_repeat('a', 121)], $userA['cookie']));
assertStatus($longProjectResponse, 400, 'long project name');
assertNestedMessage(responseJson($longProjectResponse), 'long project name');

$multibyteProjectName = str_repeat('火', 119);
$multibyteProjectResponse = $slim->handle(jsonRequest('POST', '/api/projects', ['name' => $multibyteProjectName], $userA['cookie']));
assertStatus($multibyteProjectResponse, 201, 'multibyte project name');
$multibyteProjectPayload = responseJson($multibyteProjectResponse);
assertPayloadValue($multibyteProjectPayload['project'], 'name', $multibyteProjectName, 'multibyte project name');

$createProjectResponse = $slim->handle(jsonRequest('POST', '/api/projects', ['name' => '  Alpha Project  '], $userA['cookie']));
assertStatus($createProjectResponse, 201, 'create project');
$createProjectPayload = responseJson($createProjectResponse);
$project = $createProjectPayload['project'] ?? null;
if (!is_array($project)) {
    fwrite(STDERR, "create project: expected project payload\n");
    exit(1);
}
assertPayloadValue($project, 'name', 'Alpha Project', 'create project');
if (!isset($project['id'], $project['createdAt'], $project['updatedAt']) || !is_int($project['id'])) {
    fwrite(STDERR, "create project: expected id, createdAt, updatedAt\n");
    exit(1);
}
$projectId = $project['id'];

$listResponse = $slim->handle(jsonRequest('GET', '/api/projects', null, $userA['cookie']));
assertStatus($listResponse, 200, 'project list');
$listPayload = responseJson($listResponse);
if (!isset($listPayload['projects'][0]) || !is_array($listPayload['projects'][0])) {
    fwrite(STDERR, "project list: expected one project\n");
    exit(1);
}
assertPayloadValue($listPayload['projects'][0], 'id', $projectId, 'project list');

$detailResponse = $slim->handle(jsonRequest('GET', "/api/projects/$projectId", null, $userA['cookie']));
assertStatus($detailResponse, 200, 'project detail');
$detailPayload = responseJson($detailResponse);
if (($detailPayload['project']['tasks'] ?? null) !== []) {
    fwrite(STDERR, "project detail: expected empty tasks array\n");
    exit(1);
}

$invalidPatchResponse = $slim->handle(jsonRequest('PATCH', "/api/projects/$projectId", ['name' => ''], $userA['cookie']));
assertStatus($invalidPatchResponse, 400, 'blank project patch');
assertNestedMessage(responseJson($invalidPatchResponse), 'blank project patch');

$patchProjectResponse = $slim->handle(jsonRequest('PATCH', "/api/projects/$projectId", ['name' => ' Beta Project '], $userA['cookie']));
assertStatus($patchProjectResponse, 200, 'patch project');
$patchProjectPayload = responseJson($patchProjectResponse);
assertPayloadValue($patchProjectPayload['project'], 'name', 'Beta Project', 'patch project');
if (($patchProjectPayload['project']['tasks'] ?? null) !== []) {
    fwrite(STDERR, "patch project: expected detail project with tasks\n");
    exit(1);
}

$invalidTaskResponse = $slim->handle(jsonRequest('POST', "/api/projects/$projectId/tasks", ['title' => '   '], $userA['cookie']));
assertStatus($invalidTaskResponse, 400, 'blank task title');
assertNestedMessage(responseJson($invalidTaskResponse), 'blank task title');

$longTaskResponse = $slim->handle(jsonRequest('POST', "/api/projects/$projectId/tasks", ['title' => str_repeat('t', 181)], $userA['cookie']));
assertStatus($longTaskResponse, 400, 'long task title');
assertNestedMessage(responseJson($longTaskResponse), 'long task title');

$nullCompletedTaskResponse = $slim->handle(jsonRequest('POST', "/api/projects/$projectId/tasks", [
    'title' => 'Null completed task',
    'completed' => null,
], $userA['cookie']));
assertStatus($nullCompletedTaskResponse, 400, 'null completed task create');
assertNestedMessage(responseJson($nullCompletedTaskResponse), 'null completed task create');

$nullPositionTaskResponse = $slim->handle(jsonRequest('POST', "/api/projects/$projectId/tasks", [
    'title' => 'Null position task',
    'position' => null,
], $userA['cookie']));
assertStatus($nullPositionTaskResponse, 400, 'null position task create');
assertNestedMessage(responseJson($nullPositionTaskResponse), 'null position task create');

$defaultTaskResponse = $slim->handle(jsonRequest('POST', "/api/projects/$projectId/tasks", [
    'title' => 'Default task',
], $userA['cookie']));
assertStatus($defaultTaskResponse, 201, 'default task create');
$defaultTaskPayload = responseJson($defaultTaskResponse);
assertPayloadValue($defaultTaskPayload['task'], 'completed', false, 'default task create');
assertPayloadValue($defaultTaskPayload['task'], 'position', 0, 'default task create');

$createTaskResponse = $slim->handle(jsonRequest('POST', "/api/projects/$projectId/tasks", [
    'title' => ' First task ',
    'completed' => false,
    'position' => 7,
], $userA['cookie']));
assertStatus($createTaskResponse, 201, 'create task');
$createTaskPayload = responseJson($createTaskResponse);
$task = $createTaskPayload['task'] ?? null;
if (!is_array($task)) {
    fwrite(STDERR, "create task: expected task payload\n");
    exit(1);
}
assertPayloadValue($task, 'projectId', $projectId, 'create task');
assertPayloadValue($task, 'title', 'First task', 'create task');
assertPayloadValue($task, 'completed', false, 'create task');
assertPayloadValue($task, 'position', 7, 'create task');
if (!isset($task['id'], $task['createdAt'], $task['updatedAt']) || !is_int($task['id'])) {
    fwrite(STDERR, "create task: expected id, createdAt, updatedAt\n");
    exit(1);
}
$taskId = $task['id'];

$emptyTaskPatchResponse = $slim->handle(jsonRequest('PATCH', "/api/tasks/$taskId", [], $userA['cookie']));
assertStatus($emptyTaskPatchResponse, 400, 'empty task patch');
assertNestedMessage(responseJson($emptyTaskPatchResponse), 'empty task patch');

$stringCompletedTaskPatchResponse = $slim->handle(jsonRequest('PATCH', "/api/tasks/$taskId", ['completed' => 'false'], $userA['cookie']));
assertStatus($stringCompletedTaskPatchResponse, 400, 'string completed task patch');
assertNestedMessage(responseJson($stringCompletedTaskPatchResponse), 'string completed task patch');

$stringPositionTaskPatchResponse = $slim->handle(jsonRequest('PATCH', "/api/tasks/$taskId", ['position' => 'abc'], $userA['cookie']));
assertStatus($stringPositionTaskPatchResponse, 400, 'string position task patch');
assertNestedMessage(responseJson($stringPositionTaskPatchResponse), 'string position task patch');

$completedOnlyTaskPatchResponse = $slim->handle(jsonRequest('PATCH', "/api/tasks/$taskId", ['completed' => true], $userA['cookie']));
assertStatus($completedOnlyTaskPatchResponse, 200, 'completed-only task patch');
$completedOnlyTaskPatchPayload = responseJson($completedOnlyTaskPatchResponse);
assertPayloadValue($completedOnlyTaskPatchPayload['task'], 'completed', true, 'completed-only task patch');

$patchTaskResponse = $slim->handle(jsonRequest('PATCH', "/api/tasks/$taskId", [
    'title' => ' Done task ',
    'completed' => true,
    'position' => 3,
], $userA['cookie']));
assertStatus($patchTaskResponse, 200, 'patch task');
$patchTaskPayload = responseJson($patchTaskResponse);
assertPayloadValue($patchTaskPayload['task'], 'title', 'Done task', 'patch task');
assertPayloadValue($patchTaskPayload['task'], 'completed', true, 'patch task');
assertPayloadValue($patchTaskPayload['task'], 'position', 3, 'patch task');

$invalidTaskPatchResponse = $slim->handle(jsonRequest('PATCH', "/api/tasks/$taskId", ['title' => ''], $userA['cookie']));
assertStatus($invalidTaskPatchResponse, 400, 'blank task patch');
assertNestedMessage(responseJson($invalidTaskPatchResponse), 'blank task patch');

$userBProjectResponse = $slim->handle(jsonRequest('GET', "/api/projects/$projectId", null, $userB['cookie']));
assertStatus($userBProjectResponse, 404, 'user B cannot read user A project');

$userBPatchProjectResponse = $slim->handle(jsonRequest('PATCH', "/api/projects/$projectId", ['name' => 'Stolen'], $userB['cookie']));
assertStatus($userBPatchProjectResponse, 404, 'user B cannot patch user A project');

$userBCreateTaskResponse = $slim->handle(jsonRequest('POST', "/api/projects/$projectId/tasks", ['title' => 'Stolen task'], $userB['cookie']));
assertStatus($userBCreateTaskResponse, 404, 'user B cannot create task in user A project');

$userBTaskResponse = $slim->handle(jsonRequest('PATCH', "/api/tasks/$taskId", ['completed' => false], $userB['cookie']));
assertStatus($userBTaskResponse, 404, 'user B cannot patch user A task');

$userBDeleteTaskResponse = $slim->handle(jsonRequest('DELETE', "/api/tasks/$taskId", null, $userB['cookie']));
assertStatus($userBDeleteTaskResponse, 404, 'user B cannot delete user A task');

$userBDeleteProjectResponse = $slim->handle(jsonRequest('DELETE', "/api/projects/$projectId", null, $userB['cookie']));
assertStatus($userBDeleteProjectResponse, 404, 'user B cannot delete user A project');

$deleteTaskResponse = $slim->handle(jsonRequest('DELETE', "/api/tasks/$taskId", null, $userA['cookie']));
assertStatus($deleteTaskResponse, 200, 'delete task');
assertPayloadValue(responseJson($deleteTaskResponse), 'deleted', true, 'delete task');

$deleteMissingTaskResponse = $slim->handle(jsonRequest('DELETE', "/api/tasks/$taskId", null, $userA['cookie']));
assertStatus($deleteMissingTaskResponse, 404, 'delete missing task');

$deleteProjectResponse = $slim->handle(jsonRequest('DELETE', "/api/projects/$projectId", null, $userA['cookie']));
assertStatus($deleteProjectResponse, 200, 'delete project');
assertPayloadValue(responseJson($deleteProjectResponse), 'deleted', true, 'delete project');

$deletedProjectResponse = $slim->handle(jsonRequest('GET', "/api/projects/$projectId", null, $userA['cookie']));
assertStatus($deletedProjectResponse, 404, 'deleted project detail');

echo "project api ok\n";
