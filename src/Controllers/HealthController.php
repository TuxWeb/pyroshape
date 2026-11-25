<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController
{
    public function getHealth(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode(['status' => 'ok', 'service' => 'pyroshape'], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
