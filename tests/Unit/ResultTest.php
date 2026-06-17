<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ResultTest extends TestCase
{
    public function testConvertedIsOk(): void
    {
        $r = new ModernFormats_Result(ModernFormats_Result::CONVERTED, dest: '/x/a.webp', backup: '/b/a.jpg');
        self::assertTrue($r->ok());
        self::assertSame('/x/a.webp', $r->dest);
        self::assertSame('/b/a.jpg', $r->backup);
    }

    public function testSkippedAndErrorAreNotOk(): void
    {
        self::assertFalse((new ModernFormats_Result(ModernFormats_Result::SKIPPED))->ok());
        self::assertFalse((new ModernFormats_Result(ModernFormats_Result::ERROR, error: 'boom'))->ok());
    }
}
