<?php

declare(strict_types=1);

namespace TuxWeb\PyroshapeDemo\Domain\Projects;

use PDO;
use Throwable;

class ProjectRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProjects(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, users_id, name, description, created_at, updated_at
             FROM projects
             WHERE users_id = :users_id
             ORDER BY updated_at DESC, id DESC'
        );
        $statement->execute(['users_id' => $userId]);

        return array_map(
            fn (array $row): array => $this->projectPayload($row),
            $statement->fetchAll()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createProject(int $userId, string $name, ?string $description): array
    {
        $now = $this->now();
        $statement = $this->pdo->prepare(
            'INSERT INTO projects (users_id, name, description, created_at, updated_at)
             VALUES (:users_id, :name, :description, :created_at, :updated_at)'
        );
        $statement->execute([
            'users_id' => $userId,
            'name' => $name,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findProject($userId, (int) $this->pdo->lastInsertId())
            ?? throw new \RuntimeException('Created project not found');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProject(int $userId, int $projectId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, users_id, name, description, created_at, updated_at
             FROM projects
             WHERE id = :id AND users_id = :users_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $projectId,
            'users_id' => $userId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->projectPayload($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProjectDetail(int $userId, int $projectId): ?array
    {
        $project = $this->findProject($userId, $projectId);
        if ($project === null) {
            return null;
        }

        $project['tasks'] = $this->listTasks($userId, $projectId);

        return $project;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateProject(int $userId, int $projectId, string $name, ?string $description): ?array
    {
        if ($this->findProject($userId, $projectId) === null) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'UPDATE projects
             SET name = :name, description = :description, updated_at = :updated_at
             WHERE id = :id AND users_id = :users_id'
        );
        $statement->execute([
            'id' => $projectId,
            'users_id' => $userId,
            'name' => $name,
            'description' => $description,
            'updated_at' => $this->now(),
        ]);

        return $this->findProjectDetail($userId, $projectId);
    }

    public function deleteProject(int $userId, int $projectId): bool
    {
        if ($this->findProject($userId, $projectId) === null) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $deleteTasks = $this->pdo->prepare('DELETE FROM tasks WHERE projects_id = :projects_id');
            $deleteTasks->execute(['projects_id' => $projectId]);

            $deleteProject = $this->pdo->prepare('DELETE FROM projects WHERE id = :id AND users_id = :users_id');
            $deleteProject->execute([
                'id' => $projectId,
                'users_id' => $userId,
            ]);
            $deleted = $deleteProject->rowCount() > 0;
            $this->pdo->commit();

            return $deleted;
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createTask(
        int $userId,
        int $projectId,
        string $title,
        ?bool $completed = null,
        ?int $position = null
    ): ?array {
        if ($this->findProject($userId, $projectId) === null) {
            return null;
        }

        $now = $this->now();
        $position ??= $this->nextTaskPosition($projectId);
        $statement = $this->pdo->prepare(
            'INSERT INTO tasks (projects_id, title, completed, position, created_at, updated_at)
             VALUES (:projects_id, :title, :completed, :position, :created_at, :updated_at)'
        );
        $statement->execute([
            'projects_id' => $projectId,
            'title' => $title,
            'completed' => $completed === true ? 1 : 0,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findTask($userId, (int) $this->pdo->lastInsertId());
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    public function updateTask(int $userId, int $taskId, array $fields): ?array
    {
        if ($this->findTask($userId, $taskId) === null) {
            return null;
        }

        $sets = ['updated_at = :updated_at'];
        $params = [
            'id' => $taskId,
            'users_id' => $userId,
            'updated_at' => $this->now(),
        ];

        if (array_key_exists('title', $fields)) {
            $sets[] = 'title = :title';
            $params['title'] = (string) $fields['title'];
        }
        if (array_key_exists('completed', $fields)) {
            $sets[] = 'completed = :completed';
            $params['completed'] = $fields['completed'] === true ? 1 : 0;
        }
        if (array_key_exists('position', $fields)) {
            $sets[] = 'position = :position';
            $params['position'] = (int) $fields['position'];
        }

        $statement = $this->pdo->prepare(
            'UPDATE tasks
             SET ' . implode(', ', $sets) . '
             WHERE id = :id
               AND projects_id IN (
                   SELECT id FROM projects WHERE users_id = :users_id
               )'
        );
        $statement->execute($params);

        return $this->findTask($userId, $taskId);
    }

    public function deleteTask(int $userId, int $taskId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM tasks
             WHERE id = :id
               AND projects_id IN (
                   SELECT id FROM projects WHERE users_id = :users_id
               )'
        );
        $statement->execute([
            'id' => $taskId,
            'users_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listTasks(int $userId, int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT tasks.id, tasks.projects_id, tasks.title, tasks.completed, tasks.position,
                    tasks.created_at, tasks.updated_at
             FROM tasks
             INNER JOIN projects ON projects.id = tasks.projects_id
             WHERE tasks.projects_id = :projects_id AND projects.users_id = :users_id
             ORDER BY tasks.position ASC, tasks.id ASC'
        );
        $statement->execute([
            'projects_id' => $projectId,
            'users_id' => $userId,
        ]);

        return array_map(
            fn (array $row): array => $this->taskPayload($row),
            $statement->fetchAll()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findTask(int $userId, int $taskId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT tasks.id, tasks.projects_id, tasks.title, tasks.completed, tasks.position,
                    tasks.created_at, tasks.updated_at
             FROM tasks
             INNER JOIN projects ON projects.id = tasks.projects_id
             WHERE tasks.id = :id AND projects.users_id = :users_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $taskId,
            'users_id' => $userId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->taskPayload($row) : null;
    }

    private function nextTaskPosition(int $projectId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(position) + 1, 0) AS next_position FROM tasks WHERE projects_id = :projects_id');
        $statement->execute(['projects_id' => $projectId]);
        $row = $statement->fetch();

        return is_array($row) ? (int) $row['next_position'] : 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function projectPayload(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'description' => $row['description'] === null ? null : (string) $row['description'],
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function taskPayload(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'projectId' => (int) $row['projects_id'],
            'title' => (string) $row['title'],
            'completed' => (bool) $row['completed'],
            'position' => (int) $row['position'],
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
