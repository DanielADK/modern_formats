<?php

use PHPUnit\Framework\TestCase;

// Test doubles: write controllable bytes / simulate failures.
final class WritingEncoder implements ModernFormats_Encoder
{
    public function __construct(private string $bytes = 'RIFF....WEBP') {}

    public function encode(string $src, string $dest, int $quality): bool
    {
        file_put_contents($dest, $this->bytes);

        return true;
    }
}
final class FailEncoder implements ModernFormats_Encoder
{
    public function encode(string $src, string $dest, int $quality): bool
    {
        return false;
    }
}
final class LyingEncoder implements ModernFormats_Encoder
{
    // Claims success but writes nothing (must be treated as an error).
    public function encode(string $src, string $dest, int $quality): bool
    {
        return true;
    }
}
final class ThrowingEncoder implements ModernFormats_Encoder
{
    // Simulates a backend that throws on a corrupt source (e.g. ImagickException).
    public function encode(string $src, string $dest, int $quality): bool
    {
        throw new RuntimeException('corrupt');
    }
}

final class RecordingCopier implements ModernFormats_MetadataCopier
{
    public array $calls = [];

    public function copy(string $src, string $dest): bool
    {
        $this->calls[] = [$src, $dest];

        return true;
    }
}
final class ThrowingCopier implements ModernFormats_MetadataCopier
{
    public function copy(string $src, string $dest): bool
    {
        throw new RuntimeException('copier boom');
    }
}

/**
 * @internal
 *
 * @coversNothing
 */
final class ConverterConvertTest extends TestCase
{
    private string $work;
    private string $backup;

    protected function setUp(): void
    {
        $this->work = sys_get_temp_dir().'/mf_'.uniqid();
        $this->backup = $this->work.'/backup';
        mkdir($this->work, 0o777, true);
        mkdir($this->backup, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backup.'/*') ?: [] as $f) {
            if (is_file($f)) unlink($f);
        }
        foreach (glob($this->work.'/*') ?: [] as $f) {
            if (is_file($f)) unlink($f);
        }
        @rmdir($this->backup);
        @rmdir($this->work);
    }

    public function testSkipsUnsupportedSource(): void
    {
        $gif = $this->work.'/a.gif';
        file_put_contents($gif, 'x');
        $r = $this->converter(new WritingEncoder())->convert($gif);
        self::assertSame(ModernFormats_Result::SKIPPED, $r->status);
        self::assertFileExists($gif);
    }

    public function testErrorWhenEncoderFails(): void
    {
        $src = $this->srcJpeg();
        $r = $this->converter(new FailEncoder())->convert($src);
        self::assertSame(ModernFormats_Result::ERROR, $r->status);
        self::assertFileExists($src); // original untouched on failure
    }

    public function testErrorWhenOutputMissingDespiteTrue(): void
    {
        $src = $this->srcJpeg();
        $r = $this->converter(new LyingEncoder())->convert($src);
        self::assertSame(ModernFormats_Result::ERROR, $r->status);
        self::assertFileExists($src);
    }

    public function testErrorWhenEncoderThrows(): void
    {
        $src = $this->srcJpeg();
        $r = $this->converter(new ThrowingEncoder())->convert($src);
        self::assertSame(ModernFormats_Result::ERROR, $r->status);
        self::assertFileExists($src); // original intact when the encoder throws
    }

    public function testConvertedKeepsBackup(): void
    {
        $src = $this->srcJpeg();
        $r = $this->converter(new WritingEncoder(), ['backup_mode' => 'keep'])->convert($src);
        self::assertSame(ModernFormats_Result::CONVERTED, $r->status);
        self::assertSame($this->work.'/photo.webp', $r->dest);
        self::assertFileExists($r->dest);
        self::assertFileDoesNotExist($src);                 // original moved away
        self::assertSame($this->backup.'/photo.jpg', $r->backup);
        self::assertFileExists($r->backup);
    }

    public function testConvertedDeletesWhenModeDelete(): void
    {
        $src = $this->srcJpeg();
        $r = $this->converter(new WritingEncoder(), ['backup_mode' => 'delete'])->convert($src);
        self::assertSame(ModernFormats_Result::CONVERTED, $r->status);
        self::assertNull($r->backup);
        self::assertFileDoesNotExist($src);
        self::assertFileDoesNotExist($this->backup.'/photo.jpg');
    }

    public function testBackupCollisionGetsUniqueName(): void
    {
        file_put_contents($this->backup.'/photo.jpg', 'pre-existing');
        $src = $this->srcJpeg();
        $r = $this->converter(new WritingEncoder(), ['backup_mode' => 'keep'])->convert($src);
        self::assertSame(ModernFormats_Result::CONVERTED, $r->status);
        self::assertSame($this->backup.'/photo-1.jpg', $r->backup);
        self::assertFileExists($this->backup.'/photo-1.jpg');
    }

    public function testSkipsMissingFile(): void
    {
        $r = $this->converter(new WritingEncoder())->convert($this->work.'/nope.jpg');
        self::assertSame(ModernFormats_Result::SKIPPED, $r->status);
    }

    public function testErrorWhenOutputIsEmptyFile(): void
    {
        $src = $this->srcJpeg();
        $r = $this->converter(new WritingEncoder(''))->convert($src); // encoder writes a 0-byte file
        self::assertSame(ModernFormats_Result::ERROR, $r->status);
        self::assertFileExists($src);                              // original intact
        self::assertFileDoesNotExist($this->work.'/photo.webp'); // partial output cleaned up
    }

    public function testCopiesMetadataFromSourceToWebpOnSuccess(): void
    {
        $src = $this->srcJpeg();
        $copier = new RecordingCopier();
        $conv = new ModernFormats_Converter(
            new WritingEncoder(),
            ModernFormats_Config::sanitize(['preserve_metadata' => true]),
            $this->backup,
            $copier
        );
        $r = $conv->convert($src);
        self::assertSame(ModernFormats_Result::CONVERTED, $r->status);
        self::assertSame([[$src, $this->work.'/photo.webp']], $copier->calls);
    }

    public function testDoesNotCopyMetadataWhenDisabled(): void
    {
        $src = $this->srcJpeg();
        $copier = new RecordingCopier();
        $conv = new ModernFormats_Converter(
            new WritingEncoder(),
            ModernFormats_Config::sanitize(['preserve_metadata' => false]),
            $this->backup,
            $copier
        );
        $conv->convert($src);
        self::assertSame([], $copier->calls);
    }

    public function testDoesNotCopyMetadataWhenEncodeFails(): void
    {
        $src = $this->srcJpeg();
        $copier = new RecordingCopier();
        $conv = new ModernFormats_Converter(
            new FailEncoder(),
            ModernFormats_Config::sanitize(['preserve_metadata' => true]),
            $this->backup,
            $copier
        );
        $conv->convert($src);
        self::assertSame([], $copier->calls);
    }

    public function testCopierFailureDoesNotBreakConversion(): void
    {
        $src = $this->srcJpeg();
        $conv = new ModernFormats_Converter(
            new WritingEncoder(),
            ModernFormats_Config::sanitize(['preserve_metadata' => true]),
            $this->backup,
            new ThrowingCopier()
        );
        $r = $conv->convert($src);
        self::assertSame(ModernFormats_Result::CONVERTED, $r->status); // best-effort: still converts
        self::assertFileExists($r->dest);
    }

    public function testRenameFailureKeepsOriginalAndNullsBackup(): void
    {
        $src = $this->srcJpeg();
        $bad = $this->work.'/not-a-dir';
        file_put_contents($bad, 'x'); // a file, so rename into it must fail
        $conv = new ModernFormats_Converter(
            new WritingEncoder(),
            ModernFormats_Config::sanitize(['backup_mode' => 'keep']),
            $bad
        );
        $r = $conv->convert($src);
        self::assertSame(ModernFormats_Result::CONVERTED, $r->status);
        self::assertNull($r->backup);                          // backup failed -> null
        self::assertFileExists($src);                          // original NOT lost when backup fails
        self::assertFileExists($this->work.'/photo.webp');
    }

    private function srcJpeg(): string
    {
        $src = $this->work.'/photo.jpg';
        file_put_contents($src, 'fake-jpeg-bytes');

        return $src;
    }

    private function converter(ModernFormats_Encoder $enc, array $cfg = []): ModernFormats_Converter
    {
        return new ModernFormats_Converter($enc, ModernFormats_Config::sanitize($cfg), $this->backup);
    }
}
