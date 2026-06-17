<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__.'/include')
    ->in(__DIR__.'/tests')
    ->append([__FILE__, __DIR__.'/admin.php', __DIR__.'/main.inc.php', __DIR__.'/maintain.class.php'])
;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP82Migration' => true,
        '@PHP82Migration:risky' => true,
        // Keep `/** @var */` docblocks on `global` statements (PHPStan reads them).
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        // Plugin guard `if (...) die(...);` stays one line, never braced/expanded.
        'control_structure_braces' => false,
        'curly_braces_position' => false,
        'single_line_after_imports' => true,
        // declare(strict_types) left off: enabling it would change runtime
        // type-coercion behaviour against Piwigo core. Keep formatting-only.
        'declare_strict_types' => false,
    ])
    ->setFinder($finder)
;
