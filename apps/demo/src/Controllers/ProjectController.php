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

namespace TuxWeb\PyroshapeDemo\Controllers;

use TuxWeb\Pyroshape\Core\Middlewares\Auth\CheckValidAuthToken;
use TuxWeb\PyroshapeDemo\Domain\Projects\ProjectRepository;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProjectController
{
    public function __construct(private ProjectRepository $projects)
    {
    }

    public function getProjects(Request $request, Response $response): Response
    {
        return $this->json($response, [
            'projects' => $this->projects->listProjects($this->userId($request)),
        ]);
    }

    public function postProject(Request $request, Response $response): Response
    {
        try {
            $body = $this->requestBody($request);
            $project = $this->projects->createProject(
                $this->userId($request),
                $this->projectName($body['name'] ?? null),
                $this->projectDescription($body['description'] ?? null)
            );

            return $this->json($response, ['project' => $project], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 400);
        }
    }

    public function getProject(Request $request, Response $response, string $id): Response
    {
        $project = $this->projects->findProjectDetail($this->userId($request), (int) $id);
        if ($project === null) {
            return $this->jsonError($response, 'Project not found', 404);
        }

        return $this->json($response, ['project' => $project]);
    }

    public function patchProject(Request $request, Response $response, string $id): Response
    {
        try {
            $body = $this->requestBody($request);
            $project = $this->projects->updateProject(
                $this->userId($request),
                (int) $id,
                $this->projectName($body['name'] ?? null),
                $this->projectDescription($body['description'] ?? null)
            );
            if ($project === null) {
                return $this->jsonError($response, 'Project not found', 404);
            }

            return $this->json($response, ['project' => $project]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 400);
        }
    }

    public function deleteProject(Request $request, Response $response, string $id): Response
    {
        if (!$this->projects->deleteProject($this->userId($request), (int) $id)) {
            return $this->jsonError($response, 'Project not found', 404);
        }

        return $this->json($response, ['deleted' => true]);
    }

    public function postTask(Request $request, Response $response, string $id): Response
    {
        try {
            $body = $this->requestBody($request);
            $task = $this->projects->createTask(
                $this->userId($request),
                (int) $id,
                $this->taskTitle($body['title'] ?? null, true),
                array_key_exists('completed', $body) ? $this->boolField($body['completed'], 'completed') : null,
                array_key_exists('position', $body) ? $this->intField($body['position'], 'position') : null
            );
            if ($task === null) {
                return $this->jsonError($response, 'Project not found', 404);
            }

            return $this->json($response, ['task' => $task], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 400);
        }
    }

    public function patchTask(Request $request, Response $response, string $id): Response
    {
        try {
            $body = $this->requestBody($request);
            $fields = [];
            if (array_key_exists('title', $body)) {
                $fields['title'] = $this->taskTitle($body['title'], false);
            }
            if (array_key_exists('completed', $body)) {
                $fields['completed'] = $this->boolField($body['completed'], 'completed');
            }
            if (array_key_exists('position', $body)) {
                $fields['position'] = $this->intField($body['position'], 'position');
            }
            if ($fields === []) {
                return $this->jsonError($response, 'Task update payload is empty', 400);
            }

            $task = $this->projects->updateTask($this->userId($request), (int) $id, $fields);
            if ($task === null) {
                return $this->jsonError($response, 'Task not found', 404);
            }

            return $this->json($response, ['task' => $task]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, $exception->getMessage(), 400);
        }
    }

    public function deleteTask(Request $request, Response $response, string $id): Response
    {
        if (!$this->projects->deleteTask($this->userId($request), (int) $id)) {
            return $this->jsonError($response, 'Task not found', 404);
        }

        return $this->json($response, ['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }

        $rawBody = trim((string) $request->getBody());
        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function projectName(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '') {
            throw new InvalidArgumentException('Project name is required');
        }
        if ($this->textLength($name) > 120) {
            throw new InvalidArgumentException('Project name must be 120 characters or fewer');
        }

        return $name;
    }

    private function projectDescription(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Project description must be a string or null');
        }

        $description = trim($value);

        return $description === '' ? null : $description;
    }

    private function taskTitle(mixed $value, bool $required): string
    {
        $title = is_string($value) ? trim($value) : '';
        if ($title === '') {
            throw new InvalidArgumentException($required ? 'Task title is required' : 'Task title cannot be blank');
        }
        if ($this->textLength($title) > 180) {
            throw new InvalidArgumentException('Task title must be 180 characters or fewer');
        }

        return $title;
    }

    private function boolField(mixed $value, string $field): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException("$field must be a boolean");
        }

        return $value;
    }

    private function intField(mixed $value, string $field): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException("$field must be an integer");
        }

        return $value;
    }

    private function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        $length = preg_match_all('/./us', $value, $matches);
        if ($length !== false) {
            return $length;
        }

        return strlen($value);
    }

    private function userId(Request $request): int
    {
        return (int) $request->getAttribute(CheckValidAuthToken::UID_ATTRIBUTE, '');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        return $this->json($response, ['error' => ['message' => $message]], $status);
    }
}
