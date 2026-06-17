<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

interface ModernFormats_MetadataCopier
{
    // Copy EXIF/IPTC/XMP from $src into $dest. Best-effort: false on failure, never throws out.
    public function copy(string $src, string $dest): bool;
}

// ExifTool backend — the only one that handles WebP's EXIF/XMP chunks reliably.
// Needs exec(); on hosts where it's disabled, available() is false and the
// factory falls back to the shell-free Imagick backend.
final class ModernFormats_ExiftoolCopier implements ModernFormats_MetadataCopier
{
    public function __construct(private string $bin = 'exiftool') {}

    public static function available(): bool
    {
        return self::exec_enabled() && null !== self::which('exiftool');
    }

    // True only if exec() exists and isn't listed in disable_functions.
    public static function exec_enabled(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('exec', $disabled, true);
    }

    /**
     * Resolve a binary by probing $PATH (and common dirs) — no shell involved.
     *
     * @param null|list<string> $dirs override search dirs (testing)
     */
    public static function which(string $name, ?array $dirs = null): ?string
    {
        if (null === $dirs) {
            $path = (string) getenv('PATH');
            $dirs = '' === $path ? [] : explode(PATH_SEPARATOR, $path);
            $dirs = array_merge($dirs, ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/bin']);
        }
        foreach ($dirs as $dir) {
            $candidate = rtrim($dir, '/').'/'.$name;
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function copy(string $src, string $dest): bool
    {
        if (!is_file($src) || !is_file($dest)) {
            return false;
        }
        // Orientation excluded: rotation is already baked into the WebP pixels.
        $cmd = escapeshellarg($this->bin)
            .' -m -q -overwrite_original'
            .' -TagsFromFile '.escapeshellarg($src)
            .' -all:all --Orientation'
            .' '.escapeshellarg($dest)
            .' 2>/dev/null';
        @exec($cmd, $out, $code);

        return 0 === $code;
    }
}

// Imagick backend — copies embedded profiles without an external binary.
final class ModernFormats_ImagickCopier implements ModernFormats_MetadataCopier
{
    public static function available(): bool
    {
        return extension_loaded('imagick') && class_exists('Imagick');
    }

    public function copy(string $src, string $dest): bool
    {
        if (!is_file($src) || !is_file($dest)) {
            return false;
        }

        try {
            $from = new Imagick($src);
            $profiles = $from->getImageProfiles('*', true);
            $from->destroy();
            if ([] === $profiles) {
                return false;
            }
            $to = new Imagick($dest);
            foreach ($profiles as $name => $blob) {
                if (!is_string($blob)) {
                    continue;
                }
                $to->setImageProfile($name, $blob);
            }
            $to->writeImage($dest);
            $to->destroy();

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

// Fallback when no backend is available (GD-only host without ExifTool).
final class ModernFormats_NullCopier implements ModernFormats_MetadataCopier
{
    public function copy(string $src, string $dest): bool
    {
        return false;
    }
}

// Best available backend: ExifTool > Imagick > no-op.
function modern_formats_make_copier(): ModernFormats_MetadataCopier
{
    if (ModernFormats_ExiftoolCopier::available()) {
        return new ModernFormats_ExiftoolCopier(ModernFormats_ExiftoolCopier::which('exiftool') ?? 'exiftool');
    }
    if (ModernFormats_ImagickCopier::available()) {
        return new ModernFormats_ImagickCopier();
    }

    return new ModernFormats_NullCopier();
}
