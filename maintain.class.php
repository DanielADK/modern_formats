<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

require_once __DIR__.'/include/config.class.php';

class modern_formats_maintain extends PluginMaintain
{
    /**
     * @param string                  $plugin_version
     * @param array<int|string,mixed> $errors
     */
    public function install($plugin_version, &$errors = []): void
    {
        /** @var array<string,mixed> $conf */
        global $conf;
        if (!isset($conf[ModernFormats_Config::PARAM]) || '' === $conf[ModernFormats_Config::PARAM]) {
            conf_update_param(ModernFormats_Config::PARAM, ModernFormats_Config::defaults(), true);
        }
        $this->ensure_backup_dir();
    }

    /**
     * @param string                  $plugin_version
     * @param array<int|string,mixed> $errors
     */
    public function activate($plugin_version, &$errors = []): void
    {
        $this->ensure_backup_dir();
    }

    /**
     * @param string                  $old_version
     * @param string                  $new_version
     * @param array<int|string,mixed> $errors
     */
    public function update($old_version, $new_version, &$errors = []): void
    {
        // Re-sanitize so new config keys get their defaults across upgrades.
        $current = safe_unserialize(conf_get_param(ModernFormats_Config::PARAM, []));
        conf_update_param(ModernFormats_Config::PARAM, ModernFormats_Config::sanitize((array) $current), true);
    }

    public function uninstall(): void
    {
        // Remove config only. Backups under _data are intentionally kept so
        // originals are never destroyed by uninstalling.
        conf_delete_param(ModernFormats_Config::PARAM);
        conf_delete_param('modern_formats_skipped');
    }

    private function ensure_backup_dir(): void
    {
        /** @var array<string,mixed>&array{data_location: string} $conf */
        global $conf;
        $dir = $conf['data_location'].'modern_formats_backup/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }
        // Deny web access to the backup originals.
        $htaccess = $dir.'.htaccess';
        if (is_dir($dir) && !file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }
}
