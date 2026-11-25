<?php

declare(strict_types=1);

namespace TuxWeb\PyroshapeSkeleton;

use TuxWeb\PyroshapeSkeleton\Exceptions\ConfigNotValidException;

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
}
