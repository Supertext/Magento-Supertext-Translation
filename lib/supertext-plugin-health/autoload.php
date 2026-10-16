<?php

declare(strict_types=1);

// Autoloader for the bundled Supertext Plugin Health (written by scripts/vendor-plugin-health.sh).
spl_autoload_register(static function (string $class): void {
    $prefix = 'Supertext\\PluginHealth\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
