<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

require_once PHPWG_ROOT_PATH.'admin/include/functions.php';        // delete_element_derivatives()

require_once PHPWG_ROOT_PATH.'admin/include/functions_upload.inc.php'; // pwg_image_infos()

// Writes to Piwigo's _data/logs/ (grep for [modern_formats]).
function modern_formats_log(string $message): void
{
    /** @var null|Logger $logger */
    global $logger;
    if (null !== $logger && method_exists($logger, 'error')) {
        $logger->error('[modern_formats] '.$message);
    }
}

// Purge the old (.jpg) derivative cache, then rewrite the image row to the
// new .webp file. Order matters: the cache is keyed off the OLD path.
function modern_formats_update_image(int $image_id, string $old_rel_path, string $new_abs_path): void
{
    delete_element_derivatives(['path' => $old_rel_path]);

    $info = pwg_image_infos($new_abs_path); // width, height, filesize (kB)
    // Swap only the extension, preserving the dir-prefix style of `path` and the
    // user's original `file` name (Piwigo keeps it distinct from the hashed path).
    $new_rel = (string) preg_replace('/\.[^.\/\\\]+$/', '.webp', $old_rel_path);

    $row = pwg_db_fetch_assoc(pwg_query('SELECT file FROM '.IMAGES_TABLE.' WHERE id = '.$image_id.';'));
    $old_file = false !== $row ? $row['file'] : basename($old_rel_path);
    $new_file = (string) preg_replace('/\.[^.\/\\\]+$/', '.webp', $old_file);

    single_update(
        IMAGES_TABLE,
        [
            'path' => $new_rel,
            'file' => $new_file,
            'md5sum' => md5_file($new_abs_path),
            'filesize' => $info['filesize'],
            'width' => $info['width'],
            'height' => $info['height'],
            'rotation' => 0, // rotation baked into pixels at encode time
        ],
        ['id' => $image_id]
    );
}

/**
 * Extension predicate on the (aliased) images row.
 *
 * @param list<string> $exts
 */
function modern_formats_ext_clause(array $exts): string
{
    if ([] === $exts) {
        return '0';
    }
    $likes = array_map(
        static fn (string $e): string => "LOWER(i.path) LIKE '%.".pwg_db_real_escape_string($e)."'",
        $exts
    );

    return '('.implode(' OR ', $likes).')';
}

/**
 * JOIN + WHERE fragments restricting to an album and its sub-albums. Empty when
 * no album is selected (whole gallery).
 *
 * @return array{0: string, 1: string}
 */
function modern_formats_cat_filter(?int $cat_id): array
{
    if (null === $cat_id || 0 === $cat_id) {
        return ['', ''];
    }
    if (function_exists('get_subcat_ids')) {
        $ids = get_subcat_ids([$cat_id]);
    } else {
        $ids = [$cat_id];
        $res = pwg_query('SELECT id FROM '.CATEGORIES_TABLE." WHERE uppercats LIKE '%,".$cat_id.",%';");
        while (false !== ($r = pwg_db_fetch_assoc($res))) {
            $ids[] = (int) $r['id'];
        }
    }
    $ids = array_map('intval', $ids);
    if ([] === $ids) {
        $ids = [$cat_id];
    }
    $join = ' JOIN '.IMAGE_CATEGORY_TABLE.' ic ON ic.image_id = i.id';
    $where = ' AND ic.category_id IN ('.implode(',', $ids).')';

    return [$join, $where];
}

/**
 * @param list<string> $exts
 */
function modern_formats_count_pending(array $exts, ?int $cat_id = null): int
{
    if ([] === $exts) {
        return 0;
    }
    [$join, $cat_where] = modern_formats_cat_filter($cat_id);
    $query = 'SELECT COUNT(DISTINCT i.id) AS c FROM '.IMAGES_TABLE.' i'.$join
        .' WHERE '.modern_formats_ext_clause($exts).$cat_where.';';
    $row = pwg_db_fetch_assoc(pwg_query($query));

    return false === $row ? 0 : (int) $row['c'];
}

/**
 * @param list<string> $exts
 *
 * @return list<array{id: string, path: string}>
 */
function modern_formats_pending_rows(int $start_id, int $limit, array $exts, ?int $cat_id = null): array
{
    if ([] === $exts) {
        return [];
    }
    [$join, $cat_where] = modern_formats_cat_filter($cat_id);
    $where = modern_formats_ext_clause($exts).$cat_where;
    if ($start_id > 0) {
        $where .= ' AND i.id < '.$start_id;
    }
    $query = 'SELECT DISTINCT i.id, i.path FROM '.IMAGES_TABLE.' i'.$join
        .' WHERE '.$where.' ORDER BY i.id DESC LIMIT '.$limit.';';

    $rows = [];
    $result = pwg_query($query);
    while (false !== ($row = pwg_db_fetch_assoc($result))) {
        $rows[] = ['id' => $row['id'], 'path' => $row['path']];
    }

    return $rows;
}
