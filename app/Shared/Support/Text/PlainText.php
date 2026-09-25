<?php

namespace App\Shared\Support\Text;

/**
 * Plain text from RichEditor HTML, for excerpts, meta descriptions and
 * search results. Stored rich text has no whitespace between blocks
 * ("<p>one</p><p>two</p>", "line<br>line"), so stripping tags alone glues
 * words together ("oneTwo") — a space goes at every line/block boundary
 * first. Entities are decoded so "&mdash;"/"&quot;" don't show literally
 * (Blade's {{ }} would re-escape them), and whitespace is collapsed.
 */
class PlainText
{
    public static function fromHtml(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $spaced = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|blockquote)>#i', '$0 ', $html);

        return html_entity_decode(str($spaced)->stripTags()->squish()->toString(), ENT_QUOTES | ENT_HTML5);
    }
}
