<?php

/*
Plugin Name: Modern Formats
Version: 1.4.4
Description: Automatically converts uploaded JPEG/PNG photos to WebP (configurable quality) and bulk-converts existing photos.
Plugin URI: https://github.com/DanielADK/modern_formats
Author: Daniel Adámek
Author URI: https://github.com/DanielADK
Has Settings: true
*/

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

// The plugin id IS its folder name; it must be "modern_formats" so the
// maintenance class and settings routing match and a duplicate install under a
// second name is impossible. Refuse to run under any other folder name.
if ('modern_formats' !== basename(__DIR__)) {
    add_event_handler('init', static function (): void {
        /** @var array<string,mixed>&array{errors?: list<string>} $page */
        global $page;
        $page['errors'][] = 'The "Modern Formats" plugin folder must be named "modern_formats". Please reinstall it with that folder name.';
    });

    return;
}

/** @var array<string,mixed>&array{data_location: string} $conf */
global $conf;

define('MODERN_FORMATS_ID', 'modern_formats');
define('MODERN_FORMATS_PATH', PHPWG_PLUGINS_PATH.MODERN_FORMATS_ID.'/');
define('MODERN_FORMATS_URL', get_root_url().'plugins/'.MODERN_FORMATS_ID.'/');
define('MODERN_FORMATS_BACKUP_DIR', $conf['data_location'].'modern_formats_backup/');

// Convert each newly uploaded photo (file lazy-loaded only when the event fires).
add_event_handler(
    'loc_end_add_uploaded_file',
    'modern_formats_on_upload',
    EVENT_HANDLER_PRIORITY_NEUTRAL,
    MODERN_FORMATS_PATH.'include/events.inc.php'
);

// Register bulk-conversion web service methods.
add_event_handler(
    'ws_add_methods',
    'modern_formats_add_ws_methods',
    EVENT_HANDLER_PRIORITY_NEUTRAL,
    MODERN_FORMATS_PATH.'include/ws.inc.php'
);

// Restore the picture-page EXIF panel for converted WebP: Piwigo's exif_read_data()
// can't read WebP, so supply EXIF from the embedded chunk when its native read is empty.
add_event_handler(
    'format_exif_data',
    'modern_formats_format_exif_data',
    EVENT_HANDLER_PRIORITY_NEUTRAL,
    MODERN_FORMATS_PATH.'include/display.inc.php'
);

// Restore the picture-page IPTC panel for converted WebP: Piwigo's IPTC reader is
// JPEG-only with no seam, so append the panel from the embedded XMP.
add_event_handler(
    'loc_end_picture',
    'modern_formats_picture_iptc',
    EVENT_HANDLER_PRIORITY_NEUTRAL,
    MODERN_FORMATS_PATH.'include/display.inc.php'
);
