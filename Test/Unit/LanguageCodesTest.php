<?php

declare(strict_types=1);

namespace Supertext\Translation\Test\Unit;

use PHPUnit\Framework\TestCase;
use Supertext\Translation\Api\LanguageCodes;
use Supertext\Translation\Api\SupertextException;

/**
 * Magento locales → Supertext codes, as Config::targetCode() and sourceCode() use them: the store
 * view's Supertext language, else an exact match of the locale, else unsupported_language. No
 * default regions, no guessing.
 */
final class LanguageCodesTest extends TestCase
{
    public function testLocalesThatAreSupertextCodesAreUsedAsTheyAre(): void
    {
        self::assertSame('de-CH', LanguageCodes::target('de_CH'));
        self::assertSame('fr-FR', LanguageCodes::target('fr_fr'));
        self::assertSame('it-CH', LanguageCodes::target('it_CH'));
        self::assertSame('pt-BR', LanguageCodes::target('pt_BR'));
        self::assertSame('zh-Hans', LanguageCodes::target('zh_hans'));
    }

    public function testOnlyTheSdksDefaultsAreGuessed(): void
    {
        // Since SDK 0.4.0 a target with an obvious default gets it.
        foreach (['de' => 'de-DE', 'es_ES' => 'es', 'nl_NL' => 'nl', 'zh_Hans_CN' => 'zh-Hans', 'sl_SI' => 'sl'] as $locale => $code) {
            self::assertSame($code, LanguageCodes::targetOrNull($locale), $locale);
        }

        foreach (['de_LI', 'en_IE', 'fr_BE', 'sr_RS', 'xx_YY'] as $locale) {
            self::assertNull(LanguageCodes::targetOrNull($locale), $locale);
        }

        try {
            LanguageCodes::target('fr_BE');
            self::fail('expected unsupported_language');
        } catch (SupertextException $e) {
            self::assertStringStartsWith('The language "%1" has no Supertext language yet.', $e->template());
            self::assertSame(['fr_BE'], $e->parameters());
            self::assertStringContainsString('Stores → Configuration → Supertext → Translation → Language', $e->getMessage());
        }
    }

    public function testTheStoreViewSettingWins(): void
    {
        self::assertSame('es', LanguageCodes::target('es_ES', 'es'));
        self::assertSame('fr-CH', LanguageCodes::target('fr_FR', 'fr-CH'));
        self::assertSame('nb', LanguageCodes::target('nb_NO', ' nb '));
        // A code Supertext adds later is sent as it is.
        self::assertSame('uk', LanguageCodes::target('uk_UA', 'uk'));
        self::assertSame('de-CH', LanguageCodes::target('', 'de-CH'));
    }

    public function testSources(): void
    {
        self::assertSame('en', LanguageCodes::source('en_US'));
        self::assertSame('en', LanguageCodes::source('en_GB'));
        self::assertSame('de', LanguageCodes::source('de_CH'));
        self::assertSame('zh', LanguageCodes::source('zh_Hans_CN'));
        self::assertSame('', LanguageCodes::source(''));
        // One setting for both directions: a mapped target is sent in its source form.
        self::assertSame('de', LanguageCodes::source('xx_XX', 'de-CH'));
        self::assertNull(LanguageCodes::sourceOrNull('xx_XX'));

        try {
            LanguageCodes::source('xx_XX');
            self::fail('expected unsupported_language');
        } catch (SupertextException $e) {
            self::assertStringStartsWith('The source language "%1"', $e->template());
            self::assertSame(['xx_XX'], $e->parameters());
        }
    }

    public function testPolitenessAndNames(): void
    {
        self::assertTrue(LanguageCodes::supportsPoliteness('de-CH'));
        self::assertFalse(LanguageCodes::supportsPoliteness('en-US'));
        self::assertFalse(LanguageCodes::supportsPoliteness('gsw-u-sd-chzh'));
        self::assertNull(LanguageCodes::supportsPoliteness('uk'));
        self::assertSame('German (Switzerland)', LanguageCodes::name('de-CH'));
        self::assertSame('', LanguageCodes::name('uk'));
        self::assertContains('de-CH', array_column(LanguageCodes::targets(), 'code'));
        self::assertSame('de-CH', LanguageCodes::match('de_ch'));
        self::assertNull(LanguageCodes::match('de'));
    }
}
