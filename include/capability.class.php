<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

final class ModernFormats_Capability
{
    /** @return array{ok: bool, library: ?string, reason: string} */
    public static function detect(): array
    {
        if (extension_loaded('imagick') && class_exists('Imagick')
            && in_array('WEBP', (array) Imagick::queryFormats('WEBP'), true)) {
            return ['ok' => true, 'library' => 'imagick', 'reason' => ''];
        }

        if (function_exists('gd_info') && (bool) (gd_info()['WebP Support'] ?? false)) {
            return ['ok' => true, 'library' => 'gd', 'reason' => ''];
        }

        return [
            'ok' => false,
            'library' => null,
            'reason' => 'No WebP-capable image library found (need GD built with WebP, or the Imagick extension with WEBP support).',
        ];
    }
}
