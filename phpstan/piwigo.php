<?php

// Type stubs for the Piwigo core symbols this plugin calls. Consumed by PHPStan
// only; not loaded at runtime. Empty bodies keep the parser happy.

/** @return mixed a DB result handle */
function pwg_query(string $query) {}

/**
 * @param mixed $result
 * @return array<string,string>|false
 */
function pwg_db_fetch_assoc($result) {}

function pwg_db_real_escape_string(string $s): string {}

/**
 * @param mixed $default
 * @return mixed
 */
function conf_get_param(string $param, $default = null) {}

/** @param mixed $value */
function conf_update_param(string $param, $value, bool $serialize = false): void {}

function conf_delete_param(string $param): void {}

/**
 * @param mixed $value
 * @return mixed
 */
function safe_unserialize($value) {}

/**
 * @param array<string,mixed> $datas
 * @param array<string,mixed> $where
 */
function single_update(string $table, array $datas, array $where): void {}

/** @param array{path:string} $element */
function delete_element_derivatives(array $element): void {}

/** @return array{width:int,height:int,filesize:int} */
function pwg_image_infos(string $path): array {}

/**
 * @param array<int,int> $cat_ids
 * @return array<int,int>
 */
function get_subcat_ids(array $cat_ids): array {}

function l10n(string $key): string {}

function get_root_url(): string {}

function get_pwg_token(): string {}

function check_pwg_token(): void {}

function check_status(int $level): void {}

function load_language(string $filename, string $dirname = ''): bool {}

/**
 * @param callable|string $callback
 * @return mixed
 */
function add_event_handler(string $event, $callback, int $priority = 50, string $include_path = '') {}

/**
 * @param mixed $data
 * @return mixed
 */
function trigger_change(string $event, $data = null) {}

class pwg_image
{
    public function __construct(string $source, ?string $library = null) {}

    public function set_compression_quality(int $quality): void {}

    public function rotate(int $angle): void {}

    public function write(string $destination): bool {}

    public function destroy(): void {}

    public static function get_rotation_angle(string $source): ?int {}
}

class PwgError
{
    public function __construct(int $code, string $message) {}
}

class PluginMaintain
{
    public function __construct(string $plugin_id) {}
}

class Template
{
    /**
     * @param array<string,mixed>|string $tpl_var
     * @param mixed                       $value
     */
    public function assign($tpl_var, $value = null): void {}

    public function set_filename(string $handle, string $filename): void {}

    public function assign_var_from_handle(string $varname, string $handle): void {}
}

class Logger
{
    public function error(string $message): void {}
}

class PwgServer
{
    /**
     * @param array<string,mixed> $params
     * @param array<string,mixed> $options
     */
    public function addMethod(string $name, callable $callback, array $params, string $description, string $include_path = '', array $options = []): void {}
}
