<?php

namespace Tests\Unit\Support;

use App\Shared\Support\Text\PlainText;
use PHPUnit\Framework\TestCase;

/**
 * Excerpts, meta descriptions and search results for Music, Podcast and
 * Poetry/Prose all go through PlainText::fromHtml().
 */
class PlainTextTest extends TestCase
{
    public function test_paragraphs_and_line_breaks_become_spaces(): void
    {
        $this->assertSame(
            'First paragraph. Second line Third line Last',
            PlainText::fromHtml('<p>First paragraph.</p><p>Second line<br>Third line<br/></p><p></p><h2>Last</h2>'),
        );
    }

    public function test_entities_are_decoded_and_whitespace_collapsed(): void
    {
        $this->assertSame(
            'Loud—urgent "quoted" & more',
            PlainText::fromHtml("<p>Loud&mdash;urgent   &quot;quoted&quot;\n &amp; more</p>"),
        );
    }

    public function test_inline_tags_do_not_add_spaces_inside_words(): void
    {
        $this->assertSame('Emphasised word here', PlainText::fromHtml('<p><strong>Emph</strong>asised <em>word</em> here</p>'));
    }

    public function test_blank_input_gives_an_empty_string(): void
    {
        $this->assertSame('', PlainText::fromHtml(null));
        $this->assertSame('', PlainText::fromHtml(''));
    }
}
