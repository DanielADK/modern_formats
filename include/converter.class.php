<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

/**
 * @phpstan-import-type ModernFormatsConfig from ModernFormats_Config
 */
final class ModernFormats_Converter
{
    /**
     * @param ModernFormatsConfig $config
     */
    public function __construct(
        private ModernFormats_Encoder $encoder,
        private array $config,
        private string $backup_dir,
        private ?ModernFormats_MetadataCopier $copier = null,
    ) {}

    public function is_supported_source(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ModernFormats_Config::enabled_exts($this->config), true);
    }

    public function webp_path(string $src): string
    {
        return (string) preg_replace('/\.[^.\/\\\]+$/', '.webp', $src);
    }

    public function backup_path(string $src): string
    {
        return rtrim($this->backup_dir, '/').'/'.basename($src);
    }

    public function convert(string $src): ModernFormats_Result
    {
        if (!is_file($src) || !$this->is_supported_source($src)) {
            return new ModernFormats_Result(ModernFormats_Result::SKIPPED);
        }

        $dest = $this->webp_path($src);

        try {
            $ok = $this->encoder->encode($src, $dest, $this->config['quality']);
        } catch (Throwable $e) {
            $ok = false;
        }
        if (!$ok || !is_file($dest) || 0 === filesize($dest)) {
            if (is_file($dest)) {
                @unlink($dest);
            }

            return new ModernFormats_Result(ModernFormats_Result::ERROR, error: 'encode failed: '.$src);
        }

        // Copy metadata into the WebP while $src still exists. Best-effort.
        if (($this->config['preserve_metadata'] ?? true) && null !== $this->copier) {
            try {
                $this->copier->copy($src, $dest);
            } catch (Throwable $e) {
            }
        }

        $backup = null;
        if (($this->config['backup_mode'] ?? 'keep') === 'keep') {
            $backup = $this->unique_backup_path($src);
            if (!@rename($src, $backup)) {
                $backup = null;
            }
        } else {
            @unlink($src);
        }

        return new ModernFormats_Result(ModernFormats_Result::CONVERTED, dest: $dest, backup: $backup);
    }

    private function unique_backup_path(string $src): string
    {
        $base = $this->backup_path($src);
        if (!file_exists($base)) {
            return $base;
        }
        $dir = dirname($base);
        $name = pathinfo($base, PATHINFO_FILENAME);
        $ext = pathinfo($base, PATHINFO_EXTENSION);
        $i = 1;
        do {
            $candidate = "{$dir}/{$name}-{$i}.{$ext}";
            ++$i;
        } while (file_exists($candidate));

        return $candidate;
    }
}
