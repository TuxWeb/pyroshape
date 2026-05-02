<?php

declare(strict_types=1);

use Dam2k\PyroshapeDemo\App;

require __DIR__ . '/../vendor/autoload.php';

(new App(__DIR__ . '/../config/config.php'))->run();
