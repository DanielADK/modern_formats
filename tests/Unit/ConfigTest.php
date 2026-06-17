<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $d = ModernFormats_Config::defaults();
        self::assertSame(80, $d['quality']);
        self::assertTrue($d['convert_jpeg']);
        self::assertTrue($d['convert_png']);
        self::assertTrue($d['auto_convert']);
        self::assertSame('keep', $d['backup_mode']);
        self::assertTrue($d['preserve_metadata']);
    }

    public function testSanitizeCastsPreserveMetadata(): void
    {
        self::assertFalse(ModernFormats_Config::sanitize(['preserve_metadata' => 0])['preserve_metadata']);
        self::assertTrue(ModernFormats_Config::sanitize(['preserve_metadata' => '1'])['preserve_metadata']);
    }

    public function testFromPostReadsPreserveMetadataByPresence(): void
    {
        self::assertTrue(ModernFormats_Config::from_post(['preserve_metadata' => 'on'])['preserve_metadata']);
        self::assertFalse(ModernFormats_Config::from_post([])['preserve_metadata']); // absent checkbox => false
    }

    public function testSanitizeClampsQualityAndCastsTypes(): void
    {
        $c = ModernFormats_Config::sanitize(['quality' => '0', 'convert_png' => 0]);
        self::assertSame(1, $c['quality']);                 // clamped up to 1
        self::assertFalse($c['convert_png']);
        self::assertSame(100, ModernFormats_Config::sanitize(['quality' => 999])['quality']); // clamped down to 100
        self::assertSame(85, ModernFormats_Config::sanitize(['quality' => '85'])['quality']); // numeric string cast
    }

    public function testSanitizeRejectsUnknownBackupModeAndKeys(): void
    {
        $c = ModernFormats_Config::sanitize(['backup_mode' => 'nuke', 'bogus' => 1]);
        self::assertSame('keep', $c['backup_mode']);
        self::assertArrayNotHasKey('bogus', $c);
    }

    public function testSanitizeAcceptsDeleteMode(): void
    {
        self::assertSame('delete', ModernFormats_Config::sanitize(['backup_mode' => 'delete'])['backup_mode']);
    }

    public function testFromPostReadsCheckboxesByPresence(): void
    {
        $c = ModernFormats_Config::from_post(['quality' => '70', 'convert_jpeg' => 'on', 'backup_mode' => 'delete']);
        self::assertSame(70, $c['quality']);
        self::assertTrue($c['convert_jpeg']);
        self::assertFalse($c['convert_png']);   // absent checkbox => false
        self::assertFalse($c['auto_convert']);  // absent checkbox => false
        self::assertSame('delete', $c['backup_mode']);
    }

    public function testEnabledExts(): void
    {
        self::assertSame(['jpg', 'jpeg', 'png'], ModernFormats_Config::enabled_exts(ModernFormats_Config::defaults()));
        self::assertSame(['png'], ModernFormats_Config::enabled_exts(['convert_jpeg' => false, 'convert_png' => true]));
        self::assertSame([], ModernFormats_Config::enabled_exts(['convert_jpeg' => false, 'convert_png' => false]));
    }
}
