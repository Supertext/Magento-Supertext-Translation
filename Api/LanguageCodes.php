<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Api;

use Supertext\Sdk\V1\Languages;
use Supertext\Sdk\V1\SupertextException as SdkException;

/**
 * Store view locales → Supertext language codes, without guessing (the Supertext SDK's rules, the
 * same in every Supertext plugin).
 *
 * Supertext accepts exactly the codes of its `GET features` list: bare sources ("en"); targets
 * regional only for German, English, French, Italian and Portuguese ("de-CH", "en-US", "pt-BR"),
 * with a script for Chinese and Serbian ("zh-Hans", "sr-Latn"), bare for the others ("es", "nl").
 *
 * A store view's *Supertext language* (Stores → Configuration → Supertext → Translation, store
 * view scope) wins; otherwise a locale that equals a Supertext code apart from case and "_"/"-" is
 * used ("de_CH" → "de-CH"); else, since SDK 0.4.0, the SDK's default target ("es_ES" → "es",
 * "zh_Hans_CN" → "zh-Hans"); anything else is the error `unsupported_language` and nothing is sent.
 * The same setting serves the source: a mapped "de-CH" is sent as "de".
 *
 * No Magento dependencies.
 */
final class LanguageCodes
{
    /**
     * Every target Supertext offers: code, English name, whether it has formal/informal.
     *
     * @return list<array{code: string, name: string, politeness: bool}>
     */
    public static function targets(): array
    {
        return Languages::TARGETS;
    }

    /** The Supertext target that equals the locale apart from case and "_"/"-", else null ("de_DE" → "de-DE", "es_ES" → null). */
    public static function match(string $locale): ?string
    {
        return Languages::matchTarget($locale);
    }

    /**
     * Target code to send: the store view's Supertext language, else an exact match of its locale.
     *
     * @throws SupertextException unsupported_language when there is neither (nothing is sent)
     */
    public static function target(string $locale, string $override = ''): string
    {
        try {
            return Languages::target(self::code($locale, $override), self::mapping($locale, $override));
        } catch (SdkException $e) {
            throw Supertext::error($e);
        }
    }

    /** The target code, or null when the store view has no Supertext language (settings, translate page). */
    public static function targetOrNull(string $locale, string $override = ''): ?string
    {
        try {
            return self::target($locale, $override);
        } catch (SupertextException) {
            return null;
        }
    }

    /**
     * Source code to send: the source form of the Supertext language, else of the locale
     * ("en_GB" → "en"). Empty stays empty (auto-detect).
     *
     * @throws SupertextException unsupported_language when there is neither
     */
    public static function source(string $locale, string $override = ''): string
    {
        try {
            return Languages::source(self::code($locale, $override), self::mapping($locale, $override));
        } catch (SdkException $e) {
            throw Supertext::error($e);
        }
    }

    /** The source code, or null when there is none. */
    public static function sourceOrNull(string $locale, string $override = ''): ?string
    {
        try {
            return self::source($locale, $override);
        } catch (SupertextException) {
            return null;
        }
    }

    /** Whether Supertext offers formal/informal for a target code (null: a code the SDK's list doesn't know). */
    public static function supportsPoliteness(string $target): ?bool
    {
        return Languages::supportsPoliteness($target);
    }

    /** The English name of a target code ("de-CH" → "German (Switzerland)"), or "" for a code the list doesn't know. */
    public static function name(string $target): string
    {
        foreach (Languages::TARGETS as $language) {
            if (strcasecmp($language['code'], trim($target)) === 0) {
                return $language['name'];
            }
        }

        return '';
    }

    /**
     * One store view's setting as the SDK's mapping (locale → Supertext code).
     *
     * @return array<string, string>
     */
    private static function mapping(string $locale, string $override): array
    {
        return trim($override) !== '' && trim($locale) !== '' ? [trim($locale) => trim($override)] : [];
    }

    /** The locale to resolve: a store view always has one; without, its setting alone. */
    private static function code(string $locale, string $override): string
    {
        return trim($locale) !== '' ? $locale : $override;
    }
}
