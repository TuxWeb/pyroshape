<?php

declare(strict_types=1);

namespace Dam2k\PyroshapeSkeleton;

use Dam2k\PyroshapeSkeleton\Exceptions\ConfigNotValidException;

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
}
