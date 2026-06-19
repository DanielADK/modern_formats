<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MetadataCopierTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/mf_meta_'.uniqid();
        mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            if (is_file($f)) unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testExiftoolCopiesExifFromJpegIntoWebp(): void
    {
        $this->requireGdWebp();
        $bin = $this->exiftool();

        // Build a JPEG and stamp a recognizable EXIF tag onto it with exiftool.
        $jpg = $this->dir.'/photo.jpg';
        $im = imagecreatetruecolor(32, 32);
        imagejpeg($im, $jpg, 90);

        $webp = $this->dir.'/photo.webp';
        imagewebp($im, $webp, 80);
        imagedestroy($im);

        exec(escapeshellarg($bin).' -overwrite_original -Make=ACME -Model=ShotBot '
            .escapeshellarg($jpg).' 2>/dev/null', $o, $code);
        self::assertSame(0, $code, 'precondition: could not stamp EXIF on source jpeg');

        // The WebP starts with no Make tag.
        $before = trim((string) shell_exec(escapeshellarg($bin).' -s3 -Make '.escapeshellarg($webp)));
        self::assertSame('', $before);

        $ok = (new ModernFormats_ExiftoolCopier($bin))->copy($jpg, $webp);
        self::assertTrue($ok);

        $make = trim((string) shell_exec(escapeshellarg($bin).' -s3 -Make '.escapeshellarg($webp)));
        $model = trim((string) shell_exec(escapeshellarg($bin).' -s3 -Model '.escapeshellarg($webp)));
        self::assertSame('ACME', $make);
        self::assertSame('ShotBot', $model);

        // Output is still a valid WebP after the metadata write.
        $info = getimagesize($webp);
        self::assertSame(IMAGETYPE_WEBP, $info[2]);
    }

    public function testExiftoolDoesNotCarryOrientation(): void
    {
        $this->requireGdWebp();
        $bin = $this->exiftool();

        $jpg = $this->dir.'/rot.jpg';
        $im = imagecreatetruecolor(32, 32);
        imagejpeg($im, $jpg, 90);
        $webp = $this->dir.'/rot.webp';
        imagewebp($im, $webp, 80);
        imagedestroy($im);

        // Rotation is baked into pixels at encode time, so Orientation must NOT
        // be carried over (it would double-rotate in viewers).
        exec(escapeshellarg($bin).' -overwrite_original -Orientation#=6 -Make=ACME '
            .escapeshellarg($jpg).' 2>/dev/null', $o, $code);
        self::assertSame(0, $code);

        self::assertTrue((new ModernFormats_ExiftoolCopier($bin))->copy($jpg, $webp));

        $orient = trim((string) shell_exec(escapeshellarg($bin).' -s3 -Orientation# '.escapeshellarg($webp)));
        self::assertSame('', $orient, 'orientation must not be copied');
        $make = trim((string) shell_exec(escapeshellarg($bin).' -s3 -Make '.escapeshellarg($webp)));
        self::assertSame('ACME', $make, 'other tags still copied');
    }

    public function testExiftoolEmbedsIptcAsReadableXmp(): void
    {
        $this->requireGdWebp();
        $bin = $this->exiftool();

        // WebP has no place for legacy IPTC-IIM, so the copier must map IPTC to XMP;
        // the reader recovers it as IPTC dataset codes.
        $jpg = $this->dir.'/iptc.jpg';
        $im = imagecreatetruecolor(32, 32);
        imagejpeg($im, $jpg, 90);
        $webp = $this->dir.'/iptc.webp';
        imagewebp($im, $webp, 80);
        imagedestroy($im);

        exec(escapeshellarg($bin).' -overwrite_original -IPTC:By-line=Jane -IPTC:City=Berlin '
            .'-IPTC:Keywords=alpha -IPTC:Keywords=beta '.escapeshellarg($jpg).' 2>/dev/null', $o, $code);
        self::assertSame(0, $code, 'precondition: could not stamp IPTC on source jpeg');

        self::assertTrue((new ModernFormats_ExiftoolCopier($bin))->copy($jpg, $webp));

        $iptc = ModernFormats_WebpMetadataReader::iptc($webp);
        self::assertSame('Jane', $iptc['2#080']);
        self::assertSame('Berlin', $iptc['2#090']);
        self::assertSame('alpha, beta', $iptc['2#025']);
    }

    public function testNullCopierIsNoop(): void
    {
        self::assertFalse((new ModernFormats_NullCopier())->copy('a', 'b'));
    }

    public function testWhichResolvesExecutableWithoutShell(): void
    {
        $bin = $this->dir.'/mytool';
        file_put_contents($bin, "#!/bin/sh\n");
        chmod($bin, 0o755);

        self::assertSame($bin, ModernFormats_ExiftoolCopier::which('mytool', [$this->dir]));
        self::assertNull(ModernFormats_ExiftoolCopier::which('mytool', ['/no/such/dir']));
    }

    public function testWhichIgnoresNonExecutableFile(): void
    {
        $bin = $this->dir.'/plainfile';
        file_put_contents($bin, 'x');
        chmod($bin, 0o644);

        self::assertNull(ModernFormats_ExiftoolCopier::which('plainfile', [$this->dir]));
    }

    public function testMakeCopierReturnsACopier(): void
    {
        self::assertInstanceOf(ModernFormats_MetadataCopier::class, modern_formats_make_copier());
    }

    private function requireGdWebp(): void
    {
        if (!function_exists('imagewebp') || empty(gd_info()['WebP Support'])) {
            self::markTestSkipped('GD WebP not available.');
        }
    }

    private function exiftool(): string
    {
        $bin = ModernFormats_ExiftoolCopier::which('exiftool');
        if (null === $bin) {
            self::markTestSkipped('exiftool not available.');
        }

        return $bin;
    }
}
