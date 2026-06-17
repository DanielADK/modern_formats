<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

final class ModernFormats_Result
{
    public const CONVERTED = 'converted';
    public const SKIPPED = 'skipped';
    public const ERROR = 'error';

    public function __construct(
        public readonly string $status,
        public readonly ?string $dest = null,
        public readonly ?string $backup = null,
        public readonly ?string $error = null,
    ) {}

    public function ok(): bool
    {
        return self::CONVERTED === $this->status;
    }
}
