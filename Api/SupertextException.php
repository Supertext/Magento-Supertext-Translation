<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Api;

/**
 * Any failure talking to Supertext. The message is safe to show to editors.
 *
 * The English message is built from a template with Magento-style placeholders (%1, %2, …)
 * so the admin can translate it with __(): see template(), parameters() and detail().
 */
final class SupertextException extends \RuntimeException
{
    /**
     * @param list<string|int> $parameters values for %1, %2, …
     * @param string           $detail     extra text from Supertext, shown in brackets, never translated
     */
    public function __construct(
        private readonly string $template,
        private readonly array $parameters = [],
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly string $detail = '',
    ) {
        parent::__construct(self::withDetail(self::render($template, $parameters), $detail), $code, $previous);
    }

    /** The English message with its placeholders, as listed in i18n/*.csv. */
    public function template(): string
    {
        return $this->template;
    }

    /** @return list<string|int> */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function detail(): string
    {
        return $this->detail;
    }

    /** Appends Supertext's own detail, if any, to a (translated) message. */
    public static function withDetail(string $message, string $detail): string
    {
        return $detail !== '' ? $message . ' (' . $detail . ')' : $message;
    }

    /** @param list<string|int> $parameters */
    private static function render(string $template, array $parameters): string
    {
        $replace = [];

        foreach ($parameters as $i => $value) {
            $replace['%' . ($i + 1)] = (string) $value;
        }

        // strtr() replaces the longest keys first, so %10 is not read as %1 followed by 0.
        return strtr($template, $replace);
    }
}
