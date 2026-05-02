<?php

declare(strict_types=1);

namespace Dam2k\Pyroshape\Core\Database;

use PDO;
use Psr\Container\ContainerInterface;

class PdoFactory
{
    public function __invoke(ContainerInterface $container): PDO
    {
        $settings = $container->get('settings');
        $database = $settings['database'] ?? [];

        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $options = $database['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }

        return new PDO(
            (string) ($database['dsn'] ?? ''),
            $database['username'] ?? null,
            $database['password'] ?? null,
            $options + $defaults
        );
    }
}
