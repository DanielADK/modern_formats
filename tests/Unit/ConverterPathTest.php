<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConverterPathTest extends TestCase
{
    public function testIsSupportedSourceRespectsConfig(): void
    {
        $c = $this->make();
        self::assertTrue($c->is_supported_source('/x/a.jpg'));
        self::assertTrue($c->is_supported_source('/x/a.JPG'));
        self::assertTrue($c->is_supported_source('/x/a.jpeg'));
        self::assertTrue($c->is_supported_source('/x/a.png'));
        self::assertFalse($c->is_supported_source('/x/a.webp'));
        self::assertFalse($c->is_supported_source('/x/a.gif'));

        $noPng = $this->make(['convert_png' => false]);
        self::assertFalse($noPng->is_supported_source('/x/a.png'));
    }

    public function testWebpPathSwapsExtension(): void
    {
        $c = $this->make();
        self::assertSame('/x/y/a.webp', $c->webp_path('/x/y/a.jpg'));
        self::assertSame('/x/y/a.webp', $c->webp_path('/x/y/a.JPEG'));
        self::assertSame('/x/y.dir/a.webp', $c->webp_path('/x/y.dir/a.png'));
    }

    public function testBackupPathJoinsBasename(): void
    {
        $c = $this->make();
        self::assertSame('/tmp/backup/a.jpg', $c->backup_path('/x/y/a.jpg'));
    }

    private function make(array $cfg = []): ModernFormats_Converter
    {
        $encoder = new class implements ModernFormats_Encoder {
            public function encode(string $src, string $dest, int $quality): bool
            {
                return true;
            }
        };

        return new ModernFormats_Converter($encoder, ModernFormats_Config::sanitize($cfg), '/tmp/backup');
    }
}
