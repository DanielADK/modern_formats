<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class GdWebpRoundTripTest extends TestCase
{
    public function testGdProducesValidWebp(): void
    {
        if (!function_exists('imagewebp') || empty(gd_info()['WebP Support'])) {
            self::markTestSkipped('GD WebP not available.');
        }

        $dir = sys_get_temp_dir().'/mf_gd_'.uniqid();
        mkdir($dir, 0o777, true);
        $dest = $dir.'/out.webp';

        $im = imagecreatetruecolor(64, 48);
        imagefilledrectangle($im, 0, 0, 64, 48, imagecolorallocate($im, 10, 120, 200));
        self::assertTrue(imagewebp($im, $dest, 80));
        imagedestroy($im);

        self::assertFileExists($dest);
        self::assertGreaterThan(0, filesize($dest));

        // RIFF....WEBP signature
        $head = file_get_contents($dest, false, null, 0, 12);
        self::assertSame('RIFF', substr($head, 0, 4));
        self::assertSame('WEBP', substr($head, 8, 4));

        $info = getimagesize($dest);
        self::assertSame(IMAGETYPE_WEBP, $info[2]);

        unlink($dest);
        rmdir($dir);
    }
}
