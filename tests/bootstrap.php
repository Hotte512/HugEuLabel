<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for HugEuLabel.
 *
 * Strategy:
 *   1. Try the plugin-local vendor/ autoloader (present when `composer install`
 *      was run inside the plugin directory, e.g. in isolated CI jobs).
 *   2. Fall back to the Shopware workspace-root vendor/ autoloader, which is the
 *      normal case in DDEV where composer runs at /var/www/html and all plugins
 *      share a single vendor tree four levels up.
 */

$pluginAutoloader = __DIR__ . '/../vendor/autoload.php';
$workspaceAutoloader = __DIR__ . '/../../../../vendor/autoload.php';

if (file_exists($pluginAutoloader)) {
    require_once $pluginAutoloader;
} elseif (file_exists($workspaceAutoloader)) {
    require_once $workspaceAutoloader;
} else {
    fwrite(
        STDERR,
        sprintf(
            "[HugEuLabel bootstrap] ERROR: Composer autoloader not found.\n"
            . "  Tried: %s\n"
            . "  Tried: %s\n"
            . "Run 'composer install' in the plugin root or in the Shopware workspace root.\n",
            $pluginAutoloader,
            $workspaceAutoloader,
        ),
    );
    exit(1);
}
