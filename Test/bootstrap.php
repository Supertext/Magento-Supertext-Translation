<?php

/**
 * Bootstrap for the unit tests outside Magento: they cover the classes without Magento
 * dependencies (Api/, Model/Translation/FieldPlanner, …). Run: php phpunit.phar
 */

declare(strict_types=1);

// The vendored Supertext SDK and Supertext Plugin Health, as registration.php loads them in Magento.
require_once dirname(__DIR__) . '/lib/supertext-sdk/autoload.php';
require_once dirname(__DIR__) . '/lib/supertext-plugin-health/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Supertext\\Translation\\';

    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__) . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});
