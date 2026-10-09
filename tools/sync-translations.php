<?php

/**
 * Writes the regional translation files (de_CH, fr_CH, it_CH, …) from de_DE, fr_FR and it_IT.
 * Run after editing those: php tools/sync-translations.php
 */
require __DIR__ . '/RegionalTranslations.php';

$dir = dirname(__DIR__) . '/i18n';

foreach (RegionalTranslations::COPIES as $to => $from) {
    $target = RegionalTranslations::path($dir, $to);
    file_put_contents($target, RegionalTranslations::derive((string) file_get_contents(RegionalTranslations::path($dir, $from)), $to));
    echo $target, PHP_EOL;
}
