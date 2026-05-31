<?php
use PHPUnit\Framework\TestCase;

final class GdWebpRoundTripTest extends TestCase
{
    public function test_gd_produces_valid_webp(): void
    {
        if (!function_exists('imagewebp') || empty(gd_info()['WebP Support'])) {
            $this->markTestSkipped('GD WebP not available.');
        }

        $dir  = sys_get_temp_dir() . '/mf_gd_' . uniqid();
        mkdir($dir, 0777, true);
        $dest = $dir . '/out.webp';

        $im = imagecreatetruecolor(64, 48);
        imagefilledrectangle($im, 0, 0, 64, 48, imagecolorallocate($im, 10, 120, 200));
        $this->assertTrue(imagewebp($im, $dest, 80));
        imagedestroy($im);

        $this->assertFileExists($dest);
        $this->assertGreaterThan(0, filesize($dest));

        // RIFF....WEBP signature
        $head = file_get_contents($dest, false, null, 0, 12);
        $this->assertSame('RIFF', substr($head, 0, 4));
        $this->assertSame('WEBP', substr($head, 8, 4));

        $info = getimagesize($dest);
        $this->assertSame(IMAGETYPE_WEBP, $info[2]);

        unlink($dest);
        rmdir($dir);
    }
}
