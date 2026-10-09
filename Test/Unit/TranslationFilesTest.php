<?php

declare(strict_types=1);

namespace Supertext\Translation\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Supertext\Translation\Test\ModuleStrings;

require_once __DIR__ . '/../../tools/RegionalTranslations.php';

/**
 * Every phrase the module shows has a German, French and Italian translation in i18n/ with
 * the same placeholders, and the regional copies are up to date.
 */
final class TranslationFilesTest extends TestCase
{
    private const DIR = __DIR__ . '/../../i18n';

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        foreach (['de_DE', 'fr_FR', 'it_IT', ...array_keys(\RegionalTranslations::COPIES)] as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testEveryPhraseIsTranslated(string $locale): void
    {
        $file = \RegionalTranslations::path(self::DIR, $locale);
        self::assertFileExists($file);

        $rows = [];

        foreach (\RegionalTranslations::rows((string) file_get_contents($file)) as [$source, $target]) {
            self::assertArrayNotHasKey($source, $rows, "$locale: duplicate \"$source\"");
            self::assertNotSame('', trim($target), "$locale: empty translation of \"$source\"");
            self::assertSame(self::placeholders($source), self::placeholders($target), "$locale: placeholders of \"$source\"");
            $rows[$source] = $target;
        }

        $phrases = ModuleStrings::all(dirname(__DIR__, 2));

        self::assertGreaterThan(50, \count($phrases), 'the phrase scanner found too little');
        self::assertSame([], array_values(array_diff($phrases, array_keys($rows))), "missing in $locale");
        self::assertSame([], array_values(array_diff(array_keys($rows), $phrases)), "no longer used, remove from $locale");
    }

    public function testRegionalCopiesAreInSync(): void
    {
        foreach (\RegionalTranslations::COPIES as $to => $from) {
            $expected = \RegionalTranslations::derive((string) file_get_contents(\RegionalTranslations::path(self::DIR, $from)), $to);

            self::assertSame($expected, file_get_contents(\RegionalTranslations::path(self::DIR, $to)), "$to is out of date: run php tools/sync-translations.php");
        }
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all('/%\d+|<\/?\w+>|https?:\/\/\S+[^.\s)]|SUPERTEXT_[A-Z_]+|Supertext/', $text, $matches);
        $found = array_values(array_unique($matches[0]));
        sort($found);

        return $found;
    }
}
