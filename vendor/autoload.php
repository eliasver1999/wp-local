<?php
/**
 * Minimal PSR-4 autoloader for bundled dependencies.
 *
 * This plugin bundles its Composer dependencies directly (rather than
 * requiring `composer install` on each site), so this file stands in for
 * Composer's generated autoloader. Currently registers:
 *   - pkpass/pkpass (MIT) — Apple Wallet .pkpass generation — PKPass\ => vendor/pkpass/pkpass/src/
 */

spl_autoload_register(function ($class) {
    $prefixes = array(
        'PKPass\\' => __DIR__ . '/pkpass/pkpass/src/',
    );
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relative = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
