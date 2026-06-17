<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

require_once __DIR__.'/classes.inc.php';

require_once __DIR__.'/db.inc.php';

/**
 * Hooked on ws_add_methods; the service is passed by reference as $arr[0].
 *
 * @param array{0: PwgServer} $arr
 */
function modern_formats_add_ws_methods(array $arr): void
{
    $service = $arr[0];

    $service->addMethod(
        'pwg.modernFormats.getPending',
        'ws_modern_formats_get_pending',
        [
            'cat_id' => ['type' => WS_TYPE_INT | WS_TYPE_POSITIVE, 'default' => 0],
        ],
        'Counts existing photos still pending WebP conversion (optionally within an album).',
        '',
        ['admin_only' => true]
    );

    $service->addMethod(
        'pwg.modernFormats.convert',
        'ws_modern_formats_convert',
        [
            'start_id' => ['type' => WS_TYPE_INT | WS_TYPE_POSITIVE, 'default' => 0],
            'limit' => ['type' => WS_TYPE_INT | WS_TYPE_POSITIVE, 'default' => 50, 'maxValue' => 200],
            'cat_id' => ['type' => WS_TYPE_INT | WS_TYPE_POSITIVE, 'default' => 0],
            'pwg_token' => [],
        ],
        'Converts a chunk of existing photos to WebP and returns a cursor.',
        '',
        ['admin_only' => true, 'post_only' => true]
    );
}

/**
 * @param array{cat_id?: int} $params
 *
 * @return array{pending: int}
 */
function ws_modern_formats_get_pending(array $params, PwgServer &$service): array
{
    $cfg = ModernFormats_Config::load();
    $cat = $params['cat_id'] ?? 0;
    $cat_id = $cat > 0 ? $cat : null;

    return ['pending' => modern_formats_count_pending(ModernFormats_Config::enabled_exts($cfg), $cat_id)];
}

/**
 * @param array{start_id?: int, limit?: int, cat_id?: int, pwg_token?: string} $params
 *
 * @return array{processed: int, converted: int, errors: list<int>, next_id: ?int, remaining: int}|PwgError
 */
function ws_modern_formats_convert(array $params, PwgServer &$service)
{
    if (get_pwg_token() !== ($params['pwg_token'] ?? '')) {
        return new PwgError(403, 'Invalid security token');
    }

    $cfg = ModernFormats_Config::load();
    $cap = ModernFormats_Capability::detect();
    if (!$cap['ok']) {
        return new PwgError(500, $cap['reason']);
    }

    $exts = ModernFormats_Config::enabled_exts($cfg);
    $limit = $params['limit'] ?? 50;
    $cat = $params['cat_id'] ?? 0;
    $cat_id = $cat > 0 ? $cat : null;
    $rows = modern_formats_pending_rows($params['start_id'] ?? 0, $limit, $exts, $cat_id);

    $encoder = new ModernFormats_PwgImageEncoder($cap['library']);
    $converter = new ModernFormats_Converter($encoder, $cfg, MODERN_FORMATS_BACKUP_DIR, modern_formats_make_copier());

    $converted = 0;
    $errors = [];
    $next_id = null;
    foreach ($rows as $row) {
        // Guard each photo: a single bad file must not abort the whole batch.
        // $next_id still advances so the cursor makes progress past it.
        try {
            $src_abs = PHPWG_ROOT_PATH.preg_replace('#^\./#', '', $row['path']);
            $result = $converter->convert($src_abs);
            if ($result->ok() && null !== $result->dest) {
                modern_formats_update_image((int) $row['id'], $row['path'], $result->dest);
                ++$converted;
            } elseif (ModernFormats_Result::ERROR === $result->status) {
                $errors[] = (int) $row['id'];
                modern_formats_log('bulk: could not convert image '.$row['id'].' ('.$row['path'].') — '.($encoder->lastError ?? 'unreadable or unsupported image'));
            }
        } catch (Throwable $e) {
            $errors[] = (int) $row['id'];
            modern_formats_log('bulk: error on image '.$row['id'].' ('.$row['path'].') — '.$e->getMessage());
        }
        $next_id = (int) $row['id'];
    }

    return [
        'processed' => count($rows),
        'converted' => $converted,
        'errors' => $errors,
        'next_id' => count($rows) < $limit ? null : $next_id, // null => done
        'remaining' => modern_formats_count_pending($exts, $cat_id),
    ];
}
