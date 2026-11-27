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

namespace TuxWeb\PyroshapeDemo;

use TuxWeb\Pyroshape\Core\Middlewares\Auth\CheckValidAuthToken;
use TuxWeb\Pyroshape\Core\Middlewares\Auth\RequireAuth;
use TuxWeb\Pyroshape\Core\Middlewares\Auth\RequireCurrentSession;
use TuxWeb\PyroshapeDemo\Exceptions\ConfigNotValidException;
use TuxWeb\PyroshapeDemo\Controllers\ProjectController;

class App extends \TuxWeb\Pyroshape\Core\App
{
    public function __construct(string $config)
    {
        try {
            parent::__construct($config);
        } catch (\TuxWeb\Pyroshape\Core\Exceptions\ConfigNotValidException $exception) {
            throw new ConfigNotValidException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    protected function setupAppRoutes(): void
    {
        $app = $this->getSlimApp();
        $app->delete('/api/projects/{id:[0-9]+}', [ProjectController::class, 'deleteProject'])
            ->add(RequireCurrentSession::class)->setName('projects.delete');
        $app->delete('/api/tasks/{id:[0-9]+}', [ProjectController::class, 'deleteTask'])
            ->add(RequireCurrentSession::class)->setName('tasks.delete');

        $app->group('/api', function ($group): void {
            $group->get('/projects', [ProjectController::class, 'getProjects'])->setName('projects.list');
            $group->post('/projects', [ProjectController::class, 'postProject'])->setName('projects.create');
            $group->get('/projects/{id:[0-9]+}', [ProjectController::class, 'getProject'])->setName('projects.detail');
            $group->patch('/projects/{id:[0-9]+}', [ProjectController::class, 'patchProject'])->setName('projects.update');
            $group->post('/projects/{id:[0-9]+}/tasks', [ProjectController::class, 'postTask'])->setName('tasks.create');
            $group->patch('/tasks/{id:[0-9]+}', [ProjectController::class, 'patchTask'])->setName('tasks.update');
        })->add(RequireAuth::class)->add(CheckValidAuthToken::class);
    }
}
