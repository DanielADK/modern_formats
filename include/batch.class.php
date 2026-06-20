<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

final class ModernFormats_Batch
{
    // Fraction of max_execution_time a chunk may use; the rest is headroom.
    private const FRACTION = 0.7;
    // Clamp: floor leaves room for one slow photo, ceiling stays under gateway timeouts.
    private const FLOOR = 10.0;
    private const CEILING = 30.0;

    // Per-chunk wall-clock budget, clamped to [FLOOR, CEILING].
    public static function time_budget(int $max_execution_time): float
    {
        if ($max_execution_time <= 0) {
            return self::CEILING;
        }

        return min(self::CEILING, max(self::FLOOR, $max_execution_time * self::FRACTION));
    }
}
