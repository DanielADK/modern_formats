<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

interface ModernFormats_Encoder
{
    // Encode $src into a WebP file at $dest. Return false on a known failure.
    public function encode(string $src, string $dest, int $quality): bool;
}

// Production encoder: reuses Piwigo's pwg_image. Forces the capability-detected
// backend ('imagick' or 'gd') rather than 'auto', because 'auto' may pick
// external ImageMagick, which die()s on a corrupt source instead of throwing a
// catchable error. A corrupt/truncated photo must never crash the conversion.
final class ModernFormats_PwgImageEncoder implements ModernFormats_Encoder
{
    public ?string $lastError = null;

    public function __construct(private ?string $library = null) {}

    public function encode(string $src, string $dest, int $quality): bool
    {
        $this->lastError = null;

        try {
            $img = new pwg_image($src, $this->library);
            $img->set_compression_quality($quality);

            // Bake EXIF rotation into pixels; the DB row's rotation is then reset
            // to 0 (WebP carries no EXIF orientation, so this avoids double rotation).
            $angle = pwg_image::get_rotation_angle($src);
            if (null !== $angle && 0 !== $angle) {
                $img->rotate($angle);
            }

            $img->write($dest);
            $img->destroy();
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            if (is_file($dest)) {
                @unlink($dest);
            }

            return false;
        }

        // write() return value is unreliable under GD; verify the output file.
        return is_file($dest) && filesize($dest) > 0;
    }
}
