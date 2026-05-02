<?php

declare(strict_types=1);

namespace Dam2k\PyroshapeDemo;

use Dam2k\Pyroshape\Core\Middlewares\Auth\CheckValidAuthToken;
use Dam2k\Pyroshape\Core\Middlewares\Auth\RequireAuth;
use Dam2k\PyroshapeDemo\Exceptions\ConfigNotValidException;
use Dam2k\PyroshapeDemo\Controllers\ProjectController;

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
        $this->getSlimApp()->group('/api', function ($group): void {
            $group->get('/projects', [ProjectController::class, 'getProjects'])->setName('projects.list');
            $group->post('/projects', [ProjectController::class, 'postProject'])->setName('projects.create');
            $group->get('/projects/{id:[0-9]+}', [ProjectController::class, 'getProject'])->setName('projects.detail');
            $group->patch('/projects/{id:[0-9]+}', [ProjectController::class, 'patchProject'])->setName('projects.update');
            $group->delete('/projects/{id:[0-9]+}', [ProjectController::class, 'deleteProject'])->setName('projects.delete');
            $group->post('/projects/{id:[0-9]+}/tasks', [ProjectController::class, 'postTask'])->setName('tasks.create');
            $group->patch('/tasks/{id:[0-9]+}', [ProjectController::class, 'patchTask'])->setName('tasks.update');
            $group->delete('/tasks/{id:[0-9]+}', [ProjectController::class, 'deleteTask'])->setName('tasks.delete');
        })->add(RequireAuth::class)->add(CheckValidAuthToken::class);
    }
}
