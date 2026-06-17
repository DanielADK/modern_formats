<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

require_once __DIR__.'/classes.inc.php';

require_once __DIR__.'/db.inc.php';

/**
 * Hooked on loc_end_add_uploaded_file. Must never throw — a failure here must
 * not break the upload.
 *
 * @param array{id: int|string, path: string} $image_infos
 */
function modern_formats_on_upload(array $image_infos): void
{
    try {
        $cfg = ModernFormats_Config::load();
        $cap = ModernFormats_Capability::detect();
        if (!$cfg['auto_convert'] || !$cap['ok']) {
            return;
        }

        $old_rel = $image_infos['path'];
        $src_abs = PHPWG_ROOT_PATH.preg_replace('#^\./#', '', $old_rel);

        $encoder = new ModernFormats_PwgImageEncoder($cap['library']);
        $converter = new ModernFormats_Converter($encoder, $cfg, MODERN_FORMATS_BACKUP_DIR, modern_formats_make_copier());
        if (!$converter->is_supported_source($src_abs)) {
            return;
        }

        $result = $converter->convert($src_abs);
        if ($result->ok() && null !== $result->dest) {
            modern_formats_update_image((int) $image_infos['id'], $old_rel, $result->dest);
        } elseif (ModernFormats_Result::ERROR === $result->status) {
            modern_formats_log('upload: could not convert image '.$image_infos['id'].' ('.$old_rel.') — '.($encoder->lastError ?? 'unreadable or unsupported image'));
        }
    } catch (Throwable $e) {
        modern_formats_log('on_upload exception: '.$e->getMessage());
    }
}
