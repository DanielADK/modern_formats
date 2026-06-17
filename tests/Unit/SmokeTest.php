<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class SmokeTest extends TestCase
{
    public function testBootstrapDefinesRootPath(): void
    {
        self::assertTrue(defined('PHPWG_ROOT_PATH'));
    }
}
