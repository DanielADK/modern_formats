<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

check_status(ACCESS_ADMINISTRATOR);
load_language('plugin.lang', MODERN_FORMATS_PATH);

require_once MODERN_FORMATS_PATH.'include/classes.inc.php';

require_once MODERN_FORMATS_PATH.'include/db.inc.php';

/** @var Template $template */
global $template;

/** @var array<string,mixed>&array{infos?: list<string>} $page */
global $page;

$cfg = ModernFormats_Config::load();

if (isset($_POST['submit'])) {
    check_pwg_token(); // dies on invalid/missing token (Piwigo CSRF guard)
    $cfg = ModernFormats_Config::from_post($_POST);
    ModernFormats_Config::save($cfg);
    $page['infos'][] = l10n('Settings saved.');
}

$cap = ModernFormats_Capability::detect();
$pending = modern_formats_count_pending(ModernFormats_Config::enabled_exts($cfg));

// Album choices for scoped bulk conversion (0 = whole gallery). Indented by depth.
$cats = [0 => l10n('All albums')];
$cat_result = pwg_query('SELECT id, name, uppercats FROM '.CATEGORIES_TABLE.' ORDER BY global_rank ASC;');
while (is_array($cat = pwg_db_fetch_assoc($cat_result))) {
    $depth = substr_count($cat['uppercats'], ',');
    $name = trigger_change('render_category_name', $cat['name']);
    $cats[(int) $cat['id']] = str_repeat('   ', $depth).(is_string($name) ? $name : $cat['name']);
}

$template->assign([
    'MF_QUALITY' => $cfg['quality'],
    'MF_JPEG' => $cfg['convert_jpeg'] ? 'checked="checked"' : '',
    'MF_PNG' => $cfg['convert_png'] ? 'checked="checked"' : '',
    'MF_AUTO' => $cfg['auto_convert'] ? 'checked="checked"' : '',
    'MF_META' => $cfg['preserve_metadata'] ? 'checked="checked"' : '',
    'MF_BACKUP' => $cfg['backup_mode'],
    'MF_CATS' => $cats,
    'MF_CAP_OK' => $cap['ok'],
    'MF_CAP_REASON' => $cap['reason'],
    'MF_PENDING' => $pending,
    'MF_WS_URL' => get_root_url().'ws.php?format=json',
    'MF_JS' => MODERN_FORMATS_URL.'template/admin.js',
    'PWG_TOKEN' => get_pwg_token(),
]);

$template->set_filename('mf_admin', MODERN_FORMATS_PATH.'template/admin.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'mf_admin');
