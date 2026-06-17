<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

final class ModernFormats_Batch
{
    // Fraction of the PHP execution limit a single bulk request may use before
    // returning its cursor; the rest of the limit is headroom for the in-flight
    // photo plus request teardown.
    private const FRACTION = 0.7;

    // Floor (always leave room for at least one slow photo) and the cap used when
    // the host reports no execution limit (0/negative).
    private const FLOOR = 10.0;
    private const UNLIMITED_CAP = 30.0;

    // Wall-clock seconds a bulk request should process before yielding to the
    // next AJAX step, derived from max_execution_time.
    public static function time_budget(int $max_execution_time): float
    {
        if ($max_execution_time <= 0) {
            return self::UNLIMITED_CAP;
        }

        return max(self::FLOOR, $max_execution_time * self::FRACTION);
    }
}
