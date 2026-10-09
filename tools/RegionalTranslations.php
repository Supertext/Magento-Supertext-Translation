<?php

declare(strict_types=1);

/**
 * Magento reads a module's i18n/<locale>.csv for the exact locale only (de_CH doesn't fall
 * back to de_DE). i18n/de_DE.csv, fr_FR.csv and it_IT.csv are edited by hand; the regional
 * copies are generated from them by tools/sync-translations.php.
 */
final class RegionalTranslations
{
    /** regional locale => edited locale */
    public const COPIES = [
        'de_AT' => 'de_DE',
        'de_CH' => 'de_DE',
        'fr_BE' => 'fr_FR',
        'fr_CA' => 'fr_FR',
        'fr_CH' => 'fr_FR',
        'it_CH' => 'it_IT',
    ];

    public static function path(string $i18nDir, string $locale): string
    {
        return $i18nDir . '/' . $locale . '.csv';
    }

    /** The regional file's content, derived from the edited locale's file. */
    public static function derive(string $csv, string $to): string
    {
        // Swiss German spelling has no ß. The first column is English, so only translations change.
        return $to === 'de_CH' ? str_replace('ß', 'ss', $csv) : $csv;
    }

    /** @return list<array{string, string}> source => translation rows, as Magento reads them */
    public static function rows(string $csv): array
    {
        $in = fopen('php://memory', 'w+');
        fwrite($in, $csv);
        rewind($in);
        $rows = [];

        while (($row = fgetcsv($in, null, ',', '"', '\\')) !== false) {
            if ($row !== [null]) {
                $rows[] = [(string) $row[0], (string) ($row[1] ?? '')];
            }
        }

        return $rows;
    }
}
