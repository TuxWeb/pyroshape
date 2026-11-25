<?php

declare(strict_types=1);

use TuxWeb\PyroshapeSkeleton\App;

require __DIR__ . '/../vendor/autoload.php';

(new App(__DIR__ . '/../config/config.php'))->run();
