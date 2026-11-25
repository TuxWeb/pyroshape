<?php

declare(strict_types=1);

namespace TuxWeb\Pyroshape\Core\Logger;

final class LineLogFormat
{
    public static function normalize(string $format): string
    {
        if (str_ends_with($format, "\n") || str_ends_with($format, "\r\n")) {
            return $format;
        }

        return $format . PHP_EOL;
    }
}
