<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class WebpMetadataReaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/mf_read_'.uniqid();
        mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            if (is_file($f)) unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testExifReadsTagsFromWebpExifChunk(): void
    {
        $webp = $this->webp('exif.webp');
        $this->stamp($webp, ['-Make=ACME', '-Model=ShotBot', '-ISO=200']);

        $exif = ModernFormats_WebpMetadataReader::exif($webp);

        self::assertIsArray($exif);
        self::assertSame('ACME', $exif['Make']);
        self::assertSame('ShotBot', $exif['Model']);
        self::assertSame(200, (int) $exif['ISOSpeedRatings']);
    }

    public function testExifFileSectionReflectsWebpNotTempFile(): void
    {
        $webp = $this->webp('file.webp');
        $this->stamp($webp, ['-Make=ACME']);

        $exif = ModernFormats_WebpMetadataReader::exif($webp);

        // The FILE block must describe the WebP (for the FILE;FileSize field), not the
        // temporary .tif the reader feeds to exif_read_data().
        self::assertIsArray($exif);
        self::assertSame('file.webp', $exif['FILE']['FileName']);
        self::assertSame(filesize($webp), $exif['FILE']['FileSize']);
        self::assertSame('image/webp', $exif['FILE']['MimeType']);
    }

    public function testExifFlatIdentityKeysDescribeWebpNotTempFile(): void
    {
        $webp = $this->webp('flat.webp');
        $this->stamp($webp, ['-Make=ACME']);

        $exif = ModernFormats_WebpMetadataReader::exif($webp);

        // exif_read_data() ran on an internal temp .tif; its flat top-level identity
        // keys must be rewritten to the WebP, not leak the temp file name / image/tiff.
        self::assertIsArray($exif);
        self::assertSame('flat.webp', $exif['FileName']);
        self::assertSame('image/webp', $exif['MimeType']);
    }

    public function testExifReturnsNullWhenNoExifChunk(): void
    {
        $webp = $this->webp('plain.webp'); // GD output, no metadata

        self::assertNull(ModernFormats_WebpMetadataReader::exif($webp));
    }

    public function testIptcMapsXmpBackToDatasetCodes(): void
    {
        $webp = $this->webp('iptc.webp');
        $this->stamp($webp, [
            '-XMP-dc:Title=My Title',
            '-XMP-dc:Creator=Jane Doe',
            '-XMP-dc:Description=Caption text',
            '-XMP-photoshop:City=Berlin',
            '-XMP-dc:Rights=(c) 2026',
        ]);

        $iptc = ModernFormats_WebpMetadataReader::iptc($webp);

        self::assertSame('My Title', $iptc['2#005']);
        self::assertSame('Jane Doe', $iptc['2#080']);
        self::assertSame('Caption text', $iptc['2#120']);
        self::assertSame('Berlin', $iptc['2#090']);
        self::assertSame('(c) 2026', $iptc['2#116']);
    }

    public function testIptcJoinsMultiValueKeywords(): void
    {
        $webp = $this->webp('kw.webp');
        $this->stamp($webp, ['-XMP-dc:Subject=alpha', '-XMP-dc:Subject=beta']);

        $iptc = ModernFormats_WebpMetadataReader::iptc($webp);

        self::assertSame('alpha, beta', $iptc['2#025']);
    }

    public function testIptcPreservesUtf8(): void
    {
        $webp = $this->webp('utf8.webp');
        $this->stamp($webp, ['-XMP-photoshop:City=München']);

        $iptc = ModernFormats_WebpMetadataReader::iptc($webp);

        self::assertSame('München', $iptc['2#090']);
    }

    public function testIptcReturnsEmptyWhenNoXmp(): void
    {
        $webp = $this->webp('noxmp.webp');

        self::assertSame([], ModernFormats_WebpMetadataReader::iptc($webp));
    }

    public function testReadersTolerateMissingFile(): void
    {
        self::assertNull(ModernFormats_WebpMetadataReader::exif($this->dir.'/nope.webp'));
        self::assertSame([], ModernFormats_WebpMetadataReader::iptc($this->dir.'/nope.webp'));
    }

    private function webp(string $name): string
    {
        $this->requireGdWebp();
        $path = $this->dir.'/'.$name;
        $im = imagecreatetruecolor(32, 24);
        imagewebp($im, $path, 80);
        imagedestroy($im);

        return $path;
    }

    /** @param list<string> $args */
    private function stamp(string $webp, array $args): void
    {
        $bin = $this->exiftool();
        $cmd = escapeshellarg($bin).' -q -overwrite_original';
        foreach ($args as $a) {
            $cmd .= ' '.escapeshellarg($a);
        }
        $cmd .= ' '.escapeshellarg($webp).' 2>/dev/null';
        exec($cmd, $o, $code);
        self::assertSame(0, $code, 'precondition: exiftool could not stamp the webp');
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
