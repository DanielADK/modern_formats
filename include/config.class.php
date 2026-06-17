<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

/**
 * @phpstan-type ModernFormatsConfig array{
 *     quality: int,
 *     convert_jpeg: bool,
 *     convert_png: bool,
 *     auto_convert: bool,
 *     backup_mode: 'keep'|'delete',
 *     preserve_metadata: bool,
 * }
 */
final class ModernFormats_Config
{
    public const PARAM = 'modern_formats';

    /** @return ModernFormatsConfig */
    public static function defaults(): array
    {
        return [
            'quality' => 80,
            'convert_jpeg' => true,
            'convert_png' => true,
            'auto_convert' => true,
            'backup_mode' => 'keep', // keep|delete
            'preserve_metadata' => true,
        ];
    }

    /**
     * @param array<array-key,mixed> $input
     *
     * @return ModernFormatsConfig
     */
    public static function sanitize(array $input): array
    {
        $d = self::defaults();
        $merged = array_merge($d, array_intersect_key($input, $d));

        $quality = $merged['quality'];
        $merged['quality'] = is_numeric($quality) ? max(1, min(100, (int) $quality)) : 80;
        $merged['convert_jpeg'] = (bool) $merged['convert_jpeg'];
        $merged['convert_png'] = (bool) $merged['convert_png'];
        $merged['auto_convert'] = (bool) $merged['auto_convert'];
        $merged['backup_mode'] = 'delete' === $merged['backup_mode'] ? 'delete' : 'keep';
        $merged['preserve_metadata'] = (bool) $merged['preserve_metadata'];

        return $merged;
    }

    /**
     * @param array<array-key,mixed> $post
     *
     * @return ModernFormatsConfig
     */
    public static function from_post(array $post): array
    {
        return self::sanitize([
            'quality' => $post['quality'] ?? 80,
            'convert_jpeg' => isset($post['convert_jpeg']),
            'convert_png' => isset($post['convert_png']),
            'auto_convert' => isset($post['auto_convert']),
            'backup_mode' => $post['backup_mode'] ?? 'keep',
            'preserve_metadata' => isset($post['preserve_metadata']),
        ]);
    }

    /**
     * @param array<string,mixed> $cfg
     *
     * @return list<string>
     */
    public static function enabled_exts(array $cfg): array
    {
        $exts = [];
        if ((bool) ($cfg['convert_jpeg'] ?? false)) {
            $exts[] = 'jpg';
            $exts[] = 'jpeg';
        }
        if ((bool) ($cfg['convert_png'] ?? false)) {
            $exts[] = 'png';
        }

        return $exts;
    }

    // I/O wrappers (integration-tested; thin by design).
    /** @return ModernFormatsConfig */
    public static function load(): array
    {
        return self::sanitize((array) safe_unserialize(conf_get_param(self::PARAM, [])));
    }

    /** @param ModernFormatsConfig $cfg */
    public static function save(array $cfg): void
    {
        conf_update_param(self::PARAM, self::sanitize($cfg), true);
    }
}
