<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

// The vendored Supertext SDK (lib/supertext-sdk, namespace Supertext\Sdk) and Supertext Plugin Health
// (lib/supertext-plugin-health, namespace Supertext\PluginHealth): Magento's autoloader only knows the
// module's own namespace in app/code installs, so each copy brings its own.
require_once __DIR__ . '/lib/supertext-sdk/autoload.php';
require_once __DIR__ . '/lib/supertext-plugin-health/autoload.php';

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Supertext_Translation', __DIR__);
