<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CapabilityTest extends TestCase
{
    public function testDetectReturnsWellFormedContract(): void
    {
        $cap = ModernFormats_Capability::detect();
        self::assertIsBool($cap['ok']);
        self::assertArrayHasKey('library', $cap);
        self::assertIsString($cap['reason']);
        if ($cap['ok']) {
            self::assertContains($cap['library'], ['gd', 'imagick']);
            self::assertSame('', $cap['reason']);
        } else {
            self::assertNull($cap['library']);
            self::assertNotSame('', $cap['reason']);
        }
    }

    public function testDetectTrueWhenGdHasWebp(): void
    {
        if (!function_exists('gd_info') || empty(gd_info()['WebP Support'])) {
            self::markTestSkipped('GD WebP not available on this runner.');
        }
        self::assertTrue(ModernFormats_Capability::detect()['ok']);
    }
}
