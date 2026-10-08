<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Api;

/**
 * Packs many fields into one HTML document and splits the translated document apart.
 *
 * Every field travels as <div data-st-id="N">…</div>. Supertext translates each such
 * element as a unit and keeps markup and attributes, so a whole description (with its
 * paragraphs, bold words, links and Page Builder markup) goes in one element and the
 * translator sees full sentences. Plain-text fields are escaped, with line breaks as <br>.
 *
 * Rich text is cut out of the translated document as text, not re-serialised through
 * DOMDocument: libxml would URL-encode attributes such as src="{{media url=…}}" and
 * rewrite Page Builder's attribute quoting. Magento directives ({{widget …}},
 * {{store url=…}}) in the text are wrapped in translate="no" before sending.
 *
 * No Magento dependencies.
 */
final class HtmlDocument
{
    private const LINE_BREAK_MARKER = "\u{1E}";

    private const KEEP_OPEN = '<span translate="no" class="notranslate" data-st-keep="1">';

    /**
     * @param array<int, array{text: string, html: bool}> $segments
     */
    public static function build(array $segments): string
    {
        $html = "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"></head><body>\n";

        foreach ($segments as $id => $segment) {
            if ($segment['html']) {
                $content = self::protect($segment['text']);
            } else {
                $text    = str_replace(["\r\n", "\r"], "\n", $segment['text']);
                $content = str_replace("\n", '<br>', htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $html .= '<div data-st-id="' . (int) $id . '">' . $content . "</div>\n";
        }

        return $html . '</body></html>';
    }

    /**
     * @param array<int, bool> $isHtml segment id => whether it was sent as HTML
     *
     * @return array<int, string> segment id => translated text
     */
    public static function parse(string $html, array $isHtml): array
    {
        $result = [];

        foreach (self::cut($html) as $id => $inner) {
            if (($isHtml[$id] ?? false) === true) {
                $result[$id] = trim(self::unprotect($inner));

                continue;
            }

            $result[$id] = self::plainText($inner);
        }

        return $result;
    }

    /**
     * Magento template directives are code: wrap those in text so the translator leaves
     * them alone. Directives inside tags (attribute values) are not touched.
     */
    public static function protect(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }

            $parts[$i] = (string) preg_replace('/\{\{.*?\}\}/s', self::KEEP_OPEN . '$0</span>', $part);
        }

        return implode('', $parts);
    }

    public static function unprotect(string $html): string
    {
        return (string) preg_replace('#<span(?=[^>]*\bdata-st-keep\b)[^>]*>(.*?)</span>#s', '$1', $html);
    }

    /**
     * The inner HTML of every <div data-st-id="N"> at the top level, as sent back.
     *
     * @return array<int, string>
     */
    private static function cut(string $html): array
    {
        $result = [];
        $offset = 0;

        while (preg_match('/<div\b[^>]*\bdata-st-id\s*=\s*["\']?(\d+)["\']?[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $id    = (int) $m[1][0];
            $start = $m[0][1] + \strlen($m[0][0]);
            $depth = 1;
            $pos   = $start;

            while ($depth > 0 && preg_match('#<(/?)div\b[^>]*>#i', $html, $t, PREG_OFFSET_CAPTURE, $pos)) {
                $depth += $t[1][0] === '/' ? -1 : 1;
                $pos    = $t[0][1] + \strlen($t[0][0]);

                if ($depth === 0) {
                    $result[$id] = substr($html, $start, $t[0][1] - $start);
                }
            }

            if ($depth > 0) {
                // Unclosed element: take the rest of the body.
                $end         = stripos($html, '</body>', $start);
                $result[$id] = substr($html, $start, ($end === false ? \strlen($html) : $end) - $start);
                $pos         = \strlen($html);
            }

            $offset = $pos;
        }

        return $result;
    }

    /** Plain text: <br> are the real line breaks; other whitespace collapses to one space. */
    private static function plainText(string $inner): string
    {
        $text = (string) preg_replace('#<br\s*/?>#i', self::LINE_BREAK_MARKER, $inner);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = (string) preg_replace('/ ?' . self::LINE_BREAK_MARKER . ' ?/u', "\n", $text);

        return trim($text, ' ');
    }
}
