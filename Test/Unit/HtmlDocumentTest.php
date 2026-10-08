<?php

declare(strict_types=1);

namespace Supertext\Translation\Test\Unit;

use PHPUnit\Framework\TestCase;
use Supertext\Translation\Api\HtmlDocument;

final class HtmlDocumentTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $segments = [
            ['text' => 'Fish & chips <3', 'html' => false],
            ['text' => "Line one\nLine two", 'html' => false],
            ['text' => '<p>Every praline is made in <strong>Bern</strong>. <a href="https://example.com">More</a></p>', 'html' => true],
            ['text' => 'Grüezi', 'html' => false],
        ];

        $html = HtmlDocument::build($segments);
        self::assertStringContainsString('<div data-st-id="0">Fish &amp; chips &lt;3</div>', $html);
        self::assertStringContainsString('<div data-st-id="1">Line one<br>Line two</div>', $html);

        self::assertSame(array_column($segments, 'text'), HtmlDocument::parse($html, [false, false, true, false]));
    }

    public function testKeepsPageBuilderMarkupAndDirectivesByteForByte(): void
    {
        $content = '<div data-content-type="row" data-appearance="contained" data-element="main">'
            . '<div data-enable-parallax="0" data-background-images=\'{\"desktop_image\":\"{{media url=wysiwyg/bern.jpg}}\"}\' data-element="inner">'
            . '<div data-content-type="text" data-element="main"><p>Made in <strong>Bern</strong>.</p></div>'
            . '<figure><img src="{{media url=wysiwyg/box.jpg}}" alt="Praline box"></figure>'
            . '</div></div>';

        $html = HtmlDocument::build([['text' => $content, 'html' => true], ['text' => 'Title', 'html' => false]]);

        self::assertSame([0 => $content, 1 => 'Title'], HtmlDocument::parse($html, [true, false]));
    }

    public function testProtectsDirectivesInTextOnly(): void
    {
        $protected = HtmlDocument::protect('<p>See {{widget type="Magento\Cms\Block\Widget\Page\Link" page_id="2"}} and <a href="{{store url=\'contact\'}}">us</a></p>');

        self::assertStringContainsString('<span translate="no" class="notranslate" data-st-keep="1">{{widget type="Magento\Cms\Block\Widget\Page\Link" page_id="2"}}</span>', $protected);
        self::assertStringContainsString('<a href="{{store url=\'contact\'}}">', $protected);
        self::assertSame('<p>See {{widget type="Magento\Cms\Block\Widget\Page\Link" page_id="2"}} and <a href="{{store url=\'contact\'}}">us</a></p>', HtmlDocument::unprotect($protected));
    }

    public function testReadsNestedDivsAndAttributeVariants(): void
    {
        $translated = "<html><body>\n<div data-st-id=\"0\" lang=\"de\"><div><div>Innen</div></div><p>Text</p></div>\n<div data-st-id='1'>Titel &amp; mehr</div>\n</body></html>";

        self::assertSame([0 => '<div><div>Innen</div></div><p>Text</p>', 1 => 'Titel & mehr'], HtmlDocument::parse($translated, [true, false]));
    }

    public function testCollapsesWhitespaceInPlainText(): void
    {
        self::assertSame(['Bonjour le monde'], HtmlDocument::parse("<div data-st-id=\"0\">\n  Bonjour\n  le monde </div>", [false]));
    }
}
